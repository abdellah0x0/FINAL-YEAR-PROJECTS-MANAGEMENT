<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';

// Search functionality
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($searchTerm)) {
    $searchCondition = "WHERE (p.titre LIKE :search OR p.resume LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR en.nom LIKE :search OR en.prenom LIKE :search OR f.nom LIKE :search)";
    $searchParams = [':search' => "%$searchTerm%"];
}

// Pagination setup
$itemsPerPage = 9;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get total count of PFEs for pagination
try {
    $countQuery = "SELECT COUNT(*) as total FROM pfes p
                  JOIN etudiants e ON p.etudiant_id = e.id
                  LEFT JOIN enseignants en ON p.encadrant_in_id = en.id
                  LEFT JOIN filieres f ON e.fil_id = f.id
                  $searchCondition";
    
    $countStmt = $connect->prepare($countQuery);
    if (!empty($searchTerm)) {
        $countStmt->execute($searchParams);
    } else {
        $countStmt->execute();
    }
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    // Get PFEs from database with pagination
    $query = "SELECT p.*, 
                     e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.matricule,
                     en.nom AS encadrant_nom, en.prenom AS encadrant_prenom,
                     f.nom AS filiere_nom
              FROM pfes p
              JOIN etudiants e ON p.etudiant_id = e.id
              LEFT JOIN enseignants en ON p.encadrant_in_id = en.id
              LEFT JOIN filieres f ON e.fil_id = f.id
              $searchCondition
              ORDER BY p.id DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $connect->prepare($query);
    if (!empty($searchTerm)) {
        foreach ($searchParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pfes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Database connection error");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galerie des PFEs</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .search-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-form {
            display: flex;
            width: 100%;
            max-width: 600px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }
        
        .search-button {
            padding: 10px 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .search-button:hover {
            background-color: #1a252f;
        }
        
        .gallery-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            padding: 20px;
            margin-top: 20px;
            gap: 20px;
        }
        
        .pfe-card {
            width: 30%;
            min-width: 300px;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            animation: fadeIn 0.5s;
        }
        
        .pfe-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: bold;
            color: #555;
            font-size: 0.9rem;
        }
        
        .pfe-title {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .pfe-content {
            margin: 15px 0;
            line-height: 1.5;
            font-size: 0.9rem;
        }
        
        .pfe-footer {
            display: flex;
            flex-direction: column;
            margin-top: 15px;
            font-style: italic;
            color: #7f8c8d;
            font-size: 0.8rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            width: 100%;
            gap: 10px;
        }
        
        .page-link {
            padding: 8px 15px;
            border-radius: 5px;
            background: #f1f1f1;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: #ddd;
        }
        
        .page-link.active {
            background: #2c3e50;
            color: white;
        }
        
        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .pfe-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .pdf-button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .pdf-button:hover {
            background-color: #2980b9;
        }
        
        .search-results-info {
            text-align: center;
            margin: 10px 0;
            font-style: italic;
            color: #555;
        }
        
        .no-results {
            width: 100%;
            text-align: center;
            padding: 40px;
        }
        
        @media (max-width: 768px) {
            .pfe-card {
                width: 100%;
            }
        }
    </style>
</head>
<?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>
<body>
    
    <div class="search-container">
        <form class="search-form" method="get" action="">
            <input type="text" name="search" class="search-input" placeholder="Rechercher des PFEs par titre, résumé, étudiant, encadrant ou filière..." value="<?= htmlspecialchars($searchTerm) ?>">
            <button type="submit" class="search-button">Rechercher</button>
        </form>
    </div>
    
    <?php if (!empty($searchTerm)): ?>
        <div class="search-results-info">
            Résultats de recherche pour "<?= htmlspecialchars($searchTerm) ?>" - <?= $totalItems ?> PFEs trouvés
        </div>
    <?php endif; ?>
    
    <div class="gallery-container">
        <?php if (empty($pfes)): ?>
            <div class="no-results">
                <?php if (!empty($searchTerm)): ?>
                    <h3>Aucun PFE trouvé pour votre recherche "<?= htmlspecialchars($searchTerm) ?>"</h3>
                    <a href="?" class="pdf-button">Voir tous les PFEs</a>
                <?php else: ?>
                    <h3>Aucun PFE disponible pour le moment</h3>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($pfes as $pfe): ?>
                <div class="pfe-card">
                    <div class="pfe-header">
                        <span><?= htmlspecialchars($pfe['filiere_nom'] ?? 'Filière non spécifiée') ?></span>
                        <span>Année <?= date('Y', strtotime($pfe['created_at'] ?? 'now')) ?></span>
                    </div>
                    
                    <?php if (!empty($pfe['rapport'])): ?>
                        <img src="/docs/<?= htmlspecialchars($pfe['rapport']) ?>" alt="Rapport PFE" class="pfe-image" onerror="this.style.display='none'">
                    <?php endif; ?>
                    
                    <h2 class="pfe-title"><?= htmlspecialchars($pfe['titre']) ?></h2>
                    
                    <div class="pfe-content">
                        <p><?= nl2br(htmlspecialchars(mb_strimwidth($pfe['resume'], 0, 200, '...'))) ?></p>
                    </div>
                    
                    <div class="pfe-footer">
                        <span>Présenté par: <?= htmlspecialchars($pfe['etudiant_prenom'] . ' ' . $pfe['etudiant_nom']) ?></span>
                        <span>Encadré par: <?= htmlspecialchars($pfe['encadrant_prenom'] . ' ' . $pfe['encadrant_nom']) ?></span>
                        
                        <?php if (!empty($pfe['rapport'])): ?>
                            <a href="/docs/<?= htmlspecialchars($pfe['rapport']) ?>" class="pdf-button" target="_blank">
                                Voir le PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1 ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="page-link">Précédent</a>
            <?php else: ?>
                <span class="page-link disabled">Précédent</span>
            <?php endif; ?>
            
            <?php 
            // Show page numbers
            $startPage = max(1, $currentPage - 2);
            $endPage = min($totalPages, $currentPage + 2);
            
            if ($startPage > 1) {
                echo '<a href="?page=1'.(!empty($searchTerm) ? '&search='.urlencode($searchTerm) : '').'" class="page-link">1</a>';
                if ($startPage > 2) echo '<span class="page-link">...</span>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?page=<?= $i ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="page-link <?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor;
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) echo '<span class="page-link">...</span>';
                echo '<a href="?page='.$totalPages.(!empty($searchTerm) ? '&search='.urlencode($searchTerm) : '').'" class="page-link">'.$totalPages.'</a>';
            }
            ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?><?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="page-link">Suivant</a>
            <?php else: ?>
                <span class="page-link disabled">Suivant</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
</body>
<?php include($_SERVER["DOCUMENT_ROOT"]."/config/footer.php"); ?>
</html>