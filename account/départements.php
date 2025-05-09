<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';

// Récupérer les départements avec leurs chefs
$query = "SELECT d.id, d.nom, 
                 CONCAT(e.prenom, ' ', e.nom) AS chef_nom
          FROM departements d
          LEFT JOIN enseignants e ON d.chef_id = e.id
          ORDER BY d.nom";
$stmt = $connect->query($query);
$departements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nombre de filières par département
$filieresCount = [];
$countQuery = "SELECT dept_id, COUNT(*) as nb_filieres 
               FROM filieres 
               GROUP BY dept_id";
$countStmt = $connect->query($countQuery);
while ($row = $countStmt->fetch()) {
    $filieresCount[$row['dept_id']] = $row['nb_filieres'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PFE</title>

    <link rel="stylesheet" href="/assets/css/style.css">
    
    
</head>


<?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>

<style>
       
        .inpt-container {
            font-family: 'Spartan', sans-serif;
            max-width: 100%;
            margin: 0 auto;
            padding-bottom: 110px;
            
        }

        .inpt-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .inpt-main-title {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .inpt-subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        /* Grid Layout */
        .inpt-grid {
            display: flex;
            padding-left: 100px;
            
            
            
        }

        /* Cards */
        .inpt-card-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            padding-left: 10px;
            
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            width: 300px;
        }

        .inpt-card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        

        .inpt-card-body {
            padding: 20px;
            padding-left: 10px;
        }

        .inpt-card-title {
            color: #3498db;
            margin: 0 0 10px 0;
            font-size: 1.3rem;
            
        }

        .inpt-card-text {
            color: #555;
            font-size: 0.95rem;
            line-height: 1.6;
            
        }

        /* Department Specific */
        .inpt-dept-card {
            border-top: 4px solid #2c3e50;
            
            
        }

        /* Major Specific */
        .inpt-major-card {
            border-top: 4px solid #3498db;
        }

        /* Section Titles */
        .inpt-section-title {
            color: #2c3e50;
            font-size: 1.8rem;
            padding-left: 40%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .inpt-grid {
                grid-template-columns: 1fr;
            }
            
            .inpt-main-title {
                font-size: 1.8rem;
            }
        }
    </style>












<body>
    

    <div class="inpt-container">
        <header class="inpt-header">
            <h1 class="inpt-main-title">INPT - Institut National des Postes et Télécommunications</h1>
            <p class="inpt-subtitle">Découvrez nos départements</p>
        </header>
        <div>
        <h2 class="inpt-section-title" style="padding-bottom: 200px ; ">Nos Départements</h2></div>
        <section>
            
            <div class="inpt-grid">
                <?php foreach ($departements as $dept): ?>
                <article class="inpt-card-item inpt-dept-card" >
                     
                         
                    <div class="inpt-card-body" >
                        <h3 class="inpt-card-title"><?= htmlspecialchars($dept['nom']) ?></h3>
                        
                        <div class="dept-info">
                            <p><strong>Chef de département:</strong> 
                               <?= htmlspecialchars($dept['chef_nom'] ?? 'Non attribué') ?></p>
                            <p><strong>Filières:</strong> 
                               <?= $filieresCount[$dept['id']] ?? 0 ?> filière(s)</p>
                            <a href="filieres.php?dept_id=<?= $dept['id'] ?>" class="dept-link">
                                Voir les filières →
                            </a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    

    <style>
        .dept-info {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            font-size: 0.9em;
            padding-left: 10px;
        }
        .dept-link {
            display: inline-block;
            margin-top: 10px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            
        }
        .dept-link:hover {
            text-decoration: underline;
        }
    </style>

    
</body>
<?php include($_SERVER["DOCUMENT_ROOT"]."/config/footer.php"); ?>