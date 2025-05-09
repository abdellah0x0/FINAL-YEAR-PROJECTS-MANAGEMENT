<?php 
require $_SERVER["DOCUMENT_ROOT"].'/config/jwt/vendor/autoload.php';
$HOST= "localhost";
$USERNAME= "root";
$PASSWORD= "";
$DB_NAME= "pfes";

$connect= new PDO("mysql:host=localhost;dbname=pfes",$USERNAME,$PASSWORD);
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = '1a3LM3W966D6QTJ5BJb9opunkUcw_d09NCOIJb9QZTsrneqOICoMoeYUDcd_NfaQyR787PAH98Vhue5g938jdkiyIZyJICytKlbjNBtebaHljIR6-zf3A2h3uy6pCtUFl1UhXWnV6madujY4_3SyUViRwBUOP-UudUL4wnJnKYUGDKsiZePPzBGrF4_gxJMRwF9lIWyUCHSh-PRGfvT7s1mu4-5ByYlFvGDQraP4ZiG5bC1TAKO_CnPyd1hrpdzBzNW4SfjqGKmz7IvLAHmRD-2AMQHpTU-hN2vwoA-iQxwQhfnqjM0nnwtZ0urE6HjKl6GWQW-KLnhtfw5n_84IRQ';

if (!isset($_COOKIE['token'])) {
    header('location:/account/login.php');
    exit();
}

try {
    // Decode JWT token
    $decoded = JWT::decode($_COOKIE['token'], new Key($key, 'HS256'));
    
    // Check if user role is 'enseignant'
    if ($decoded->data->role !== 'enseignant') {
        header('location: /account/unauthorized.php');
    }
    $user_id = $decoded->data->user_id;
    
    // Get teacher info
    $query = "SELECT nom, prenom, email FROM enseignants WHERE id = :user_id";
    $stmt = $connect->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        die("Teacher not found");
    }
    
    $nom = $teacher['nom'];
    $prenom = $teacher['prenom'];
    
    // Handle PFE updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pfe'])) {
        $pfe_id = $_POST['pfe_id'];
        $titre = $_POST['titre'];
        $resume = $_POST['resume'];
        
        // Verify the PFE is supervised by this teacher
        $verifyQuery = "SELECT id FROM pfes WHERE id = :pfe_id AND encadrant_in_id = :enseignant_id";
        $verifyStmt = $connect->prepare($verifyQuery);
        $verifyStmt->execute([':pfe_id' => $pfe_id, ':enseignant_id' => $user_id]);
        
        if ($verifyStmt->fetch()) {
            $updateQuery = "UPDATE pfes SET titre = :titre, resume = :resume WHERE id = :pfe_id";
            $updateStmt = $connect->prepare($updateQuery);
            $updateStmt->execute([
                ':titre' => $titre,
                ':resume' => $resume,
                ':pfe_id' => $pfe_id
            ]);
            $updateSuccess = "PFE updated successfully!";
        } else {
            $updateError = "You don't have permission to update this PFE.";
        }
    }
    
    // Get PFEs supervised by this teacher
    $pfesQuery = "SELECT p.id, p.titre, p.resume, p.rapport, 
                         e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.matricule,
                         f.nom AS filiere_nom
                  FROM pfes p
                  JOIN etudiants e ON p.etudiant_id = e.id
                  LEFT JOIN filieres f ON e.fil_id = f.id
                  WHERE p.encadrant_in_id = :enseignant_id
                  ORDER BY p.id DESC";
    $pfesStmt = $connect->prepare($pfesQuery);
    $pfesStmt->execute([':enseignant_id' => $user_id]);
    $pfeFiles = $pfesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher PFE Management</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .body2 {
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .profile-container {
            width: 100%;
            max-width: 900px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
        }
        
        .profile-header {
            padding: 20px 0;
            background-color: white;
        }
        
        .profile-content {
            padding: 0 25px 25px;
        }
        
        .name {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .location {
            color: #777;
            font-size: 15px;
            margin-bottom: 15px;
        }
        
        .profession {
            font-weight: 500;
            color: #444;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .stats {
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }
        
        .stat-label {
            font-size: 13px;
            color: #777;
            margin-top: 3px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 10px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 15px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-connect {
            background-color: #020000;
            color: white;
        }
        
        .btn-connect:hover {
            background-color: #cccccc;
        }
        
        .btn-message {
            background-color: #e4e6eb;
            color: #050505;
        }
        
        .btn-message:hover {
            background-color: #d8dadf;
        }
        
        .file-list {
            margin-top: 20px;
            text-align: left;
        }
        
        .file-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-actions form {
            display: inline;
        }
        
        .upload-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            text-align: left;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
        }
        
        .modal-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>
    
    <div class="body2">
        <div class="profile-container">
            <div class="profile-content">
                <h2 class="name"><?= htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) ?></h2>
                <p class="location"><?= htmlspecialchars($teacher['email']) ?></p>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($pfeFiles) ?></div>
                        <div class="stat-label">PFEs Encadrés</div>
                    </div>
                </div>
                
                <?php if (isset($updateSuccess)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($updateSuccess) ?></div>
                <?php elseif (isset($updateError)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($updateError) ?></div>
                <?php endif; ?>
                
                <div class="file-list">
                    <h3>PFEs Encadrés</h3>
                    <?php if (empty($pfeFiles)): ?>
                        <p>Aucun PFE encadré pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($pfeFiles as $file): ?>
                            <div class="file-item">
                                <div class="file-info">
                                    <strong><?= htmlspecialchars($file['titre']) ?></strong>
                                    <div>Étudiant: <?= htmlspecialchars($file['etudiant_prenom'] . ' ' . $file['etudiant_nom']) ?> (<?= htmlspecialchars($file['matricule']) ?>)</div>
                                    <div>Filière: <?= htmlspecialchars($file['filiere_nom'] ?? 'Non spécifiée') ?></div>
                                    <p><?= nl2br(htmlspecialchars(mb_strimwidth($file['resume'], 0, 200, '...'))) ?></p>
                                    <?php if (!empty($file['rapport'])): ?>
                                        <a href="/docs/<?= htmlspecialchars($file['rapport']) ?>" class="btn btn-connect" target="_blank">
                                            Voir le PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="file-actions">
                                    <button class="btn btn-message" onclick="openEditModal(
                                        <?= $file['id'] ?>,
                                        '<?= htmlspecialchars(addslashes($file['titre'])) ?>',
                                        `<?= htmlspecialchars(addslashes($file['resume'])) ?>`
                                    )">
                                        Modifier
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="/account/logout.php" class="btn btn-message">Déconnexion</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <h3 class="modal-title">Modifier le PFE</h3>
            <form method="post" id="editForm">
                <input type="hidden" name="pfe_id" id="editPfeId">
                <div class="form-group">
                    <label for="editTitre">Titre:</label>
                    <input type="text" id="editTitre" name="titre" required>
                </div>
                <div class="form-group">
                    <label for="editResume">Résumé:</label>
                    <textarea id="editResume" name="resume" required rows="5"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-message" onclick="closeEditModal()">Annuler</button>
                    <button type="submit" name="update_pfe" class="btn btn-connect">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(id, titre, resume) {
            document.getElementById('editPfeId').value = id;
            document.getElementById('editTitre').value = titre;
            document.getElementById('editResume').value = resume;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        }
    </script>
    
    <?php include($_SERVER["DOCUMENT_ROOT"]."/config/footer.php"); ?>
</body>
</html>