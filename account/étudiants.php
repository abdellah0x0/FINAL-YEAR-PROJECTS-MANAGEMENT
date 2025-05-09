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
    
    // Check if user role is 'etudiant'
    if ($decoded->data->role !== 'etudiant') {
        header('location: /account/unauthorized.php');
    }
    $user_id = $decoded->data->user_id;
    
    // Get student info
    $query = "SELECT nom, prenom, email, tel, matricule, promotion FROM etudiants WHERE id = :user_id";
    $stmt = $connect->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die("Student not found");
    }
    
    $nom = $student['nom'];
    $prenom = $student['prenom'];
    
    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
        if (isset($_FILES['pfe_file']) && $_FILES['pfe_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $_SERVER["DOCUMENT_ROOT"].'/docs/';
            $fileName = preg_replace("/[^A-Za-z0-9._-]/", '_', basename($_FILES['pfe_file']['name']));
            $fileName = "PFE_".$student['matricule']."_".time()."_".$fileName;
            $targetPath = $uploadDir . $fileName;
            
            // Check file type (allow PDF, DOC, DOCX)
            $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            if (!in_array($fileType, ['pdf', 'doc', 'docx'])) {
                $uploadError = "Only PDF, DOC, and DOCX files are allowed.";
            } elseif (move_uploaded_file($_FILES['pfe_file']['tmp_name'], $targetPath)) {
                // Save to database
                $insertQuery = "INSERT INTO pfes (etudiant_id, titre, rapport) VALUES (:etudiant_id, :titre, :rapport)";
                $insertStmt = $connect->prepare($insertQuery);
                $insertStmt->execute([
                    ':etudiant_id' => $user_id,
                    ':titre' => $_POST['titre'] ?? 'PFE Document',
                    ':rapport' => $fileName
                ]);
                $uploadSuccess = "File uploaded successfully!";
            } else {
                $uploadError = "Error uploading file.";
            }
        } else {
            $uploadError = "Please select a valid file to upload.";
        }
    }
    
    // Handle file deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
        $fileId = $_POST['file_id'] ?? 0;
        
        // Verify the file belongs to the student
        $verifyQuery = "SELECT rapport FROM pfes WHERE id = :id AND etudiant_id = :etudiant_id";
        $verifyStmt = $connect->prepare($verifyQuery);
        $verifyStmt->execute([':id' => $fileId, ':etudiant_id' => $user_id]);
        $fileToDelete = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fileToDelete) {
            $filePath = $_SERVER["DOCUMENT_ROOT"].'/docs/'.$fileToDelete['rapport'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete from database
            $deleteQuery = "DELETE FROM pfes WHERE id = :id";
            $deleteStmt = $connect->prepare($deleteQuery);
            $deleteStmt->execute([':id' => $fileId]);
            $deleteSuccess = "File deleted successfully!";
        } else {
            $deleteError = "File not found or you don't have permission to delete it.";
        }
    }
    
    // Get student's PFE files (without created_at since it doesn't exist in your table)
    $filesQuery = "SELECT id, titre, rapport FROM pfes WHERE etudiant_id = :etudiant_id";
    $filesStmt = $connect->prepare($filesQuery);
    $filesStmt->execute([':etudiant_id' => $user_id]);
    $pfeFiles = $filesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PFE Management</title>
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
        .form-group input[type="file"] {
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
    </style>
</head>
<body>
    <?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>
    
    <div class="body2">
        <div class="profile-container">
            <div class="profile-content">
                <h2 class="name"><?= htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) ?></h2>
                <p class="location"><?= htmlspecialchars($student['email']) ?></p>
                <p class="profession"><?= htmlspecialchars($student['matricule']) ?></p>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= htmlspecialchars($student['email']) ?></div>
                        <div class="stat-label">Email</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= htmlspecialchars($student['tel']) ?></div>
                        <div class="stat-label">Numéro de téléphone</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= htmlspecialchars($student['promotion']) ?></div>
                        <div class="stat-label">Promotion</div>
                    </div>
                </div>
                
                <?php if (isset($uploadSuccess)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($uploadSuccess) ?></div>
                <?php elseif (isset($uploadError)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($uploadError) ?></div>
                <?php endif; ?>
                
                <?php if (isset($deleteSuccess)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($deleteSuccess) ?></div>
                <?php elseif (isset($deleteError)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($deleteError) ?></div>
                <?php endif; ?>
                
                <div class="upload-form">
                    <h3>Upload PFE Document</h3>
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="titre">Title:</label>
                            <input type="text" id="titre" name="titre" required>
                        </div>
                        <div class="form-group">
                            <label for="pfe_file">Document (PDF/DOC/DOCX):</label>
                            <input type="file" id="pfe_file" name="pfe_file" accept=".pdf,.doc,.docx" required>
                        </div>
                        <button type="submit" name="upload" class="btn btn-connect">Upload</button>
                    </form>
                </div>
                
                <div class="file-list">
                    <h3>My PFE Documents</h3>
                    <?php if (empty($pfeFiles)): ?>
                        <p>No documents uploaded yet.</p>
                    <?php else: ?>
                        <?php foreach ($pfeFiles as $file): ?>
                            <div class="file-item">
                                <div class="file-info">
                                    <strong><?= htmlspecialchars($file['titre']) ?></strong>
                                    <div><?= htmlspecialchars($file['rapport']) ?></div>
                                    <a href="/docs/<?= htmlspecialchars($file['rapport']) ?>" target="_blank">View</a>
                                </div>
                                <div class="file-actions">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <button type="submit" name="delete" class="btn btn-message">Delete</button>
                                    </form>
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
    
    <?php include($_SERVER["DOCUMENT_ROOT"]."/config/footer.php"); ?>
</body>
</html>