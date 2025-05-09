<body>
<!--HEADER-->
    <header id="Header">
        <a href="/"><img src="/assets/img/logo.png" alt="" class="logo"></a>
        
        <nav>
            <ul id="navbar">
                <?php
                require $_SERVER["DOCUMENT_ROOT"].'/config/jwt/vendor/autoload.php';
                require $_SERVER["DOCUMENT_ROOT"].'/config/mysql-config.php';
                use Firebase\JWT\JWT;
                use Firebase\JWT\Key;
                $key = '1a3LM3W966D6QTJ5BJb9opunkUcw_d09NCOIJb9QZTsrneqOICoMoeYUDcd_NfaQyR787PAH98Vhue5g938jdkiyIZyJICytKlbjNBtebaHljIR6-zf3A2h3uy6pCtUFl1UhXWnV6madujY4_3SyUViRwBUOP-UudUL4wnJnKYUGDKsiZePPzBGrF4_gxJMRwF9lIWyUCHSh-PRGfvT7s1mu4-5ByYlFvGDQraP4ZiG5bC1TAKO_CnPyd1hrpdzBzNW4SfjqGKmz7IvLAHmRD-2AMQHpTU-hN2vwoA-iQxwQhfnqjM0nnwtZ0urE6HjKl6GWQW-KLnhtfw5n_84IRQ';

                if (isset($_COOKIE['token'])) {
                    try {
                        $decoded = JWT::decode($_COOKIE['token'], new Key($key, 'HS256'));
                        $role = $decoded->data->role;
                        ?>
                        
                        <li><a href="/">Home</a></li>
                        
                        <?php if ($role === 'admin'): ?>
                            <li><a href="/account/admin.php">Admin Panel</a></li>
                            <li><a href="/account/filières.php">Filières</a></li>
                            <li><a href="/account/départements.php">Départements</a></li>
                            <li><a href="/account/PFEs.php">PFEs</a></li>
                        <?php elseif ($role === 'enseignant'): ?>
                            <li><a href="/account/enseignants.php">Enseignants</a></li>
                            <li><a href="/account/PFEs.php">PFEs</a></li>
                        <?php elseif ($role === 'etudiant'): ?>
                            <li><a href="/account/étudiants.php">Etudiants</a></li>
                            <li><a href="/account/PFEs.php">PFEs</a></li>
                        <?php endif; ?>
                        
                        <li><a href="/account/logout.php">Logout</a></li>
                        
                        <?php
                        if (isset($decoded->data->user_id)) {
                            $user_id = $decoded->data->user_id;
                            $query = "SELECT nom, prenom FROM ";
                            
                            if ($role === 'etudiant') {
                                $query .= "etudiants WHERE id = :user_id";
                            } elseif ($role === 'enseignant') {
                                $query .= "enseignants WHERE id = :user_id";
                            }
                            
                            if ($role !== 'admin') {
                                $stmt = $connect->prepare($query);
                                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                $stmt->execute();
                                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($user) {
                                    ?>
                                    <li><span>Welcome <?= htmlspecialchars($user['prenom']) ?> <?= htmlspecialchars($user['nom']) ?></span></li>
                                    <?php
                                }
                            } else {
                                ?>
                                <li><span>Welcome Admin</span></li>
                                <?php
                            }
                        }
                        
                    } catch (Exception $e) {
                        ?>
                        <li><a href="/">Home</a></li>
                        <li><a href="/account/départements.php">Départements</a></li>
                        <li><a href="/account/filières.php">Filières</a></li>
                        <li><a href="/account/login.php">Login</a></li>
                        <?php
                    }
                } else {
                    ?>
                    <li><a href="/">Home</a></li>
                    <li><a href="/account/départements.php">Départements</a></li>
                    <li><a href="/account/filières.php">Filières</a></li>
                    <li><a href="/account/PFEs.php">PFEs</a></li>
                    <li><a href="/account/login.php">Login</a></li>
                    <?php
                }
                ?>
            </ul>
        </nav>   
    </header>