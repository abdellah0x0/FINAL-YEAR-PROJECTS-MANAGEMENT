<?php
require_once $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';

// Récupérer les filières depuis la base de données avec leurs départements
$query = "SELECT f.id, f.nom, d.nom AS departement_nom 
          FROM filieres f
          JOIN departements d ON f.dept_id = d.id
          ORDER BY f.nom";
$stmt = $connect->query($query);
$filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        /* INPT Specific Styles - Won't conflict with existing CSS */
        .inpt-container {
            font-family: 'Spartan', sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
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
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        /* Cards */
        .inpt-card-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .inpt-card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

     
        .inpt-card-body {
            padding: 20px;
            width: 250px;
            padding-right: 30px;
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
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
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
            <p class="inpt-subtitle">Découvrez nos filières</p>
        </header>
       <div> <h2 class="inpt-section-title" style="padding-bottom:250px ; padding-left: 40%;">Nos Filières</h2> </div>
        <section>
            
            <div class="inpt-grid">
                <?php foreach ($filieres as $filiere): ?>
                <article class="inpt-card-item inpt-major-card">
                   
                    <div class="inpt-card-body">
                        <h3 class="inpt-card-title"><?= htmlspecialchars($filiere['nom']) ?></h3>
                        <p class="inpt-card-text">
                            <?= htmlspecialchars($filiere['description'] ?? 'Description non disponible') ?>
                        </p>
                        <p><small>Département: <?= htmlspecialchars($filiere['departement_nom']) ?></small></p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <?php include($_SERVER["DOCUMENT_ROOT"]."/config/footer.php"); ?>