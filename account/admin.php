<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';
require_once $_SERVER["DOCUMENT_ROOT"].'/config/jwt/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Authentication check
$key = '1a3LM3W966D6QTJ5BJb9opunkUcw_d09NCOIJb9QZTsrneqOICoMoeYUDcd_NfaQyR787PAH98Vhue5g938jdkiyIZyJICytKlbjNBtebaHljIR6-zf3A2h3uy6pCtUFl1UhXWnV6madujY4_3SyUViRwBUOP-UudUL4wnJnKYUGDKsiZePPzBGrF4_gxJMRwF9lIWyUCHSh-PRGfvT7s1mu4-5ByYlFvGDQraP4ZiG5bC1TAKO_CnPyd1hrpdzBzNW4SfjqGKmz7IvLAHmRD-2AMQHpTU-hN2vwoA-iQxwQhfnqjM0nnwtZ0urE6HjKl6GWQW-KLnhtfw5n_84IRQ';
if (!isset($_COOKIE['token'])) {
    header('location: /account/login.php');
    exit();
}

try {
    $decoded = JWT::decode($_COOKIE['token'], new Key($key, 'HS256'));
    if ($decoded->data->role !== 'admin') {
        header('location: /account/unauthorized.php');
        exit();
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Available tables and their fields
$tables = [
    'departements' => ['id', 'nom', 'chef_id'],
    'filieres' => ['id', 'nom', 'dept_id', 'coord_id'],
    'enseignants' => ['id', 'nom', 'prenom', 'email', 'dept_id'],
    'etudiants' => ['id', 'matricule', 'promotion', 'nom', 'prenom', 'tel', 'email', 'fil_id'],
    'pfes' => ['id', 'etudiant_id', 'titre', 'resume', 'organisme', 'encadrant_ex', 'email_ex', 'encadrant_in_id', 'rapport'],
    'users' => ['id', 'email', 'password', 'role', 'user_id'],
    'roles' => ['role']
];

// Current table and action
$current_table = $_GET['table'] ?? 'departements';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    
    try {
        if ($action === 'create') {
            // Handle file upload for PFE
            if ($current_table === 'pfes' && isset($_FILES['rapport'])) {
                $uploadDir = $_SERVER["DOCUMENT_ROOT"].'/docs/';
                $fileName = preg_replace("/[^A-Za-z0-9._-]/", '_', basename($_FILES['rapport']['name']));
                $fileName = "PFE_".time()."_".$fileName;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['rapport']['tmp_name'], $targetPath)) {
                    $data['rapport'] = $fileName;
                }
            }
            
            // Handle password hashing for users
            if ($current_table === 'users' && isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $fields = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $query = "INSERT INTO $current_table ($fields) VALUES ($placeholders)";
            $stmt = $connect->prepare($query);
            $stmt->execute(array_values($data));
            
            header("Location: admin.php?table=$current_table&success=1");
            exit();
            
        } elseif ($action === 'update') {
            // Handle file upload for PFE update
            if ($current_table === 'pfes' && isset($_FILES['rapport']) && $_FILES['rapport']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = $_SERVER["DOCUMENT_ROOT"].'/docs/';
                $fileName = preg_replace("/[^A-Za-z0-9._-]/", '_', basename($_FILES['rapport']['name']));
                $fileName = "PFE_".time()."_".$fileName;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['rapport']['tmp_name'], $targetPath)) {
                    // Delete old file if exists
                    $oldFileQuery = "SELECT rapport FROM pfes WHERE id = ?";
                    $oldFileStmt = $connect->prepare($oldFileQuery);
                    $oldFileStmt->execute([$data['id']]);
                    $oldFile = $oldFileStmt->fetchColumn();
                    
                    if ($oldFile && file_exists($uploadDir.$oldFile)) {
                        unlink($uploadDir.$oldFile);
                    }
                    
                    $data['rapport'] = $fileName;
                }
            }
            
            // Handle password update for users
            if ($current_table === 'users' && isset($data['password']) && !empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            } elseif ($current_table === 'users') {
                // Don't update password if empty
                unset($data['password']);
            }
            
            $set = [];
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $set[] = "$key = ?";
                }
            }
            $query = "UPDATE $current_table SET " . implode(', ', $set) . " WHERE id = ?";
            $values = array_values(array_diff_key($data, ['id' => '']));
            $values[] = $data['id'];
            $stmt = $connect->prepare($query);
            $stmt->execute($values);
            
            header("Location: admin.php?table=$current_table&success=1");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Handle delete action
if ($action === 'delete' && $id) {
    try {
        // Handle file deletion for PFE
        if ($current_table === 'pfes') {
            $fileQuery = "SELECT rapport FROM pfes WHERE id = ?";
            $fileStmt = $connect->prepare($fileQuery);
            $fileStmt->execute([$id]);
            $fileName = $fileStmt->fetchColumn();
            
            if ($fileName && file_exists($_SERVER["DOCUMENT_ROOT"].'/docs/'.$fileName)) {
                unlink($_SERVER["DOCUMENT_ROOT"].'/docs/'.$fileName);
            }
        }
        
        $query = "DELETE FROM $current_table WHERE id = ?";
        $stmt = $connect->prepare($query);
        $stmt->execute([$id]);
        
        header("Location: admin.php?table=$current_table&success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Function to get related items for dropdowns
function getRelatedItems($connect, $field) {
    $relatedTable = str_replace('_id', '', $field);
    
    // Special cases for foreign keys
    if ($relatedTable === 'chef' || $relatedTable === 'coord' || $relatedTable === 'encadrant_in') {
        $relatedTable = 'enseignants';
    } elseif ($relatedTable === 'fil') {
        $relatedTable = 'filieres';
    } elseif ($relatedTable === 'dept') {
        $relatedTable = 'departements';
    } elseif ($relatedTable === 'etudiant') {
        $relatedTable = 'etudiants';
    }
    
    try {
        // For enseignants, show both name and surname
        if ($relatedTable === 'enseignants') {
            $query = "SELECT id, CONCAT(nom, ' ', prenom) AS nom FROM $relatedTable";
        } 
        // For etudiants, show matricule and name
        elseif ($relatedTable === 'etudiants') {
            $query = "SELECT id, CONCAT(matricule, ' - ', nom, ' ', prenom) AS nom FROM $relatedTable";
        }
        // For other tables, just show the name
        else {
            $query = "SELECT id, nom FROM $relatedTable";
        }
        
        return $connect->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 200px;
            background-color: #333;
            color: white;
            padding: 20px 0;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #555;
        }
        .sidebar a.active {
            background-color: #4CAF50;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .action-btn {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 3px;
            white-space: nowrap;
        }
        .edit-btn {
            background-color: #4CAF50;
            color: white;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        .add-btn {
            background-color: #2196F3;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 100px;
        }
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        .back-btn {
            background-color: #ccc;
            color: black;
            padding: 10px 15px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .success-msg {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error-msg {
            background-color: #f2dede;
            color: #a94442;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 35px;
            cursor: pointer;
        }
        .actions-cell {
            white-space: nowrap;
        }
    </style>
    <script>
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.querySelector(`#${inputId} + .password-toggle`);
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '👁️';
            } else {
                input.type = 'password';
                toggle.textContent = '👁️‍🗨️';
            }
        }
    </script>
</head>
<?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h3 style="padding: 0 20px;">Admin Panel</h3>
            <?php foreach ($tables as $table => $fields): ?>
                <a href="?table=<?= $table ?>" class="<?= $current_table === $table ? 'active' : '' ?>">
                    <?= ucfirst($table) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Main content -->
        <div class="main-content">
            <?php if (isset($_GET['success'])): ?>
                <div class="success-msg">
                    Opération effectuée avec succès!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="error-msg">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <h2><?= ucfirst($current_table) ?></h2>
            
            <?php if ($action === 'list'): ?>
                <a href="?table=<?= $current_table ?>&action=create" class="add-btn">Ajouter</a>
                
                <?php
                $query = "SELECT * FROM $current_table";
                $items = $connect->query($query)->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($tables[$current_table] as $field): ?>
                                <th><?= ucfirst(str_replace('_', ' ', $field)) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <?php foreach ($tables[$current_table] as $field): ?>
                                <td>
                                    <?php if ($field === 'rapport' && !empty($item[$field])): ?>
                                        <a href="/docs/<?= htmlspecialchars($item[$field]) ?>" target="_blank">Voir PDF</a>
                                    <?php elseif (strpos($field, '_id') !== false && !empty($item[$field])): ?>
                                        <?php
                                        // Display related item name instead of ID
                                        $relatedTable = str_replace('_id', '', $field);
                                        if ($relatedTable === 'chef' || $relatedTable === 'coord' || $relatedTable === 'encadrant_in') {
                                            $relatedTable = 'enseignants';
                                        } elseif ($relatedTable === 'fil') {
                                            $relatedTable = 'filieres';
                                        } elseif ($relatedTable === 'dept') {
                                            $relatedTable = 'departements';
                                        } elseif ($relatedTable === 'etudiant') {
                                            $relatedTable = 'etudiants';
                                        }
                                        
                                        if ($relatedTable === 'enseignants') {
                                            $query = "SELECT CONCAT(nom, ' ', prenom) AS name FROM $relatedTable WHERE id = ?";
                                        } elseif ($relatedTable === 'etudiants') {
                                            $query = "SELECT CONCAT(matricule, ' - ', nom, ' ', prenom) AS name FROM $relatedTable WHERE id = ?";
                                        } else {
                                            $query = "SELECT nom AS name FROM $relatedTable WHERE id = ?";
                                        }
                                        
                                        $stmt = $connect->prepare($query);
                                        $stmt->execute([$item[$field]]);
                                        $relatedItem = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo htmlspecialchars($relatedItem['name'] ?? '');
                                        ?>
                                    <?php elseif ($field === 'password'): ?>
                                        ********
                                    <?php else: ?>
                                        <?= htmlspecialchars($item[$field] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="actions-cell">
                                <a href="?table=<?= $current_table ?>&action=edit&id=<?= $item['id'] ?>" 
                                   class="action-btn edit-btn">Modifier</a>
                                <a href="?table=<?= $current_table ?>&action=delete&id=<?= $item['id'] ?>" 
                                   class="action-btn delete-btn"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet enregistrement?')">Supprimer</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <a href="?table=<?= $current_table ?>" class="back-btn">Retour</a>
                
                <?php
                $item = [];
                if ($action === 'edit' && $id) {
                    $query = "SELECT * FROM $current_table WHERE id = ?";
                    $stmt = $connect->prepare($query);
                    $stmt->execute([$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                ?>
                
                <form method="POST" action="?table=<?= $current_table ?>&action=<?= $action === 'edit' ? 'update' : 'create' ?>" 
                      <?= $current_table === 'pfes' ? 'enctype="multipart/form-data"' : '' ?>>
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <?php endif; ?>
                    
                    <?php foreach ($tables[$current_table] as $field): ?>
                        <?php if ($field !== 'id'): ?>
                            <div class="form-group <?= $field === 'password' ? 'password-field' : '' ?>">
                                <label for="<?= $field ?>"><?= ucfirst(str_replace('_', ' ', $field)) ?></label>
                                <?php if ($field === 'rapport'): ?>
                                    <input type="file" id="<?= $field ?>" name="<?= $field ?>">
                                    <?php if (!empty($item[$field])): ?>
                                        <p>Fichier actuel: <a href="/docs/<?= htmlspecialchars($item[$field]) ?>" target="_blank"><?= htmlspecialchars($item[$field]) ?></a></p>
                                    <?php endif; ?>
                                <?php elseif (strpos($field, '_id') !== false): ?>
                                    <?php $relatedItems = getRelatedItems($connect, $field); ?>
                                    <select id="<?= $field ?>" name="<?= $field ?>">
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($relatedItems as $relatedItem): ?>
                                            <option value="<?= $relatedItem['id'] ?>" <?= (isset($item[$field]) && $item[$field] == $relatedItem['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($relatedItem['nom']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field === 'resume'): ?>
                                    <textarea id="<?= $field ?>" name="<?= $field ?>"><?= htmlspecialchars($item[$field] ?? '') ?></textarea>
                                <?php elseif ($field === 'password'): ?>
                                    <input type="password" id="<?= $field ?>" name="<?= $field ?>" value="">
                                    <span class="password-toggle" onclick="togglePasswordVisibility('<?= $field ?>')">👁️‍🗨️</span>
                                    <?php if ($action === 'edit'): ?>
                                        <small>Laisser vide pour ne pas modifier</small>
                                    <?php endif; ?>
                                <?php elseif ($field === 'role' && $current_table === 'users'): ?>
                                    <?php $roles = $connect->query("SELECT role FROM roles")->fetchAll(PDO::FETCH_ASSOC); ?>
                                    <select id="<?= $field ?>" name="<?= $field ?>">
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role['role'] ?>" <?= (isset($item[$field]) && $item[$field] == $role['role']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role['role']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" id="<?= $field ?>" name="<?= $field ?>" 
                                           value="<?= htmlspecialchars($item[$field] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="submit-btn">
                        <?= $action === 'edit' ? 'Mettre à jour' : 'Créer' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>