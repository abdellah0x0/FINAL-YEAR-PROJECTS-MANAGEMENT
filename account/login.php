<?php 

require $_SERVER["DOCUMENT_ROOT"].'/config/jwt/vendor/autoload.php';
use Firebase\JWT\JWT;


$error = '';


if(isset($_POST["login"]))
{
	
    $HOST= "localhost";
    $USERNAME= "root";
    $PASSWORD= "";
    $DB_NAME= "pfes";


$connect= new PDO("mysql:host=localhost;dbname=pfes",$USERNAME,$PASSWORD);

	if(empty($_POST["email"])){
		$error = 'Please Enter Email Details';
	} else if(empty($_POST["password"])){
		$error = 'Please Enter Password Details';
	} else {
		$query = "SELECT * FROM users WHERE email = ?";
		$statement = $connect->prepare($query);
		$statement->execute([$_POST["email"]]);
		$data = $statement->fetch(PDO::FETCH_ASSOC);
		if($data){
			if($data['password'] ===  $_POST['password']){
				$key = '1a3LM3W966D6QTJ5BJb9opunkUcw_d09NCOIJb9QZTsrneqOICoMoeYUDcd_NfaQyR787PAH98Vhue5g938jdkiyIZyJICytKlbjNBtebaHljIR6-zf3A2h3uy6pCtUFl1UhXWnV6madujY4_3SyUViRwBUOP-UudUL4wnJnKYUGDKsiZePPzBGrF4_gxJMRwF9lIWyUCHSh-PRGfvT7s1mu4-5ByYlFvGDQraP4ZiG5bC1TAKO_CnPyd1hrpdzBzNW4SfjqGKmz7IvLAHmRD-2AMQHpTU-hN2vwoA-iQxwQhfnqjM0nnwtZ0urE6HjKl6GWQW-KLnhtfw5n_84IRQ';
				$token = JWT::encode(
					array(
						'iat'		=>	time(),
						'nbf'		=>	time(),
						'exp'		=>	time() + 3600,
						'data'	=> array(
							'user_id'	=>	$data['user_id'],
							'email'	=>	$data['email'],
                            'role'	=>	$data['role']
						)
					),
					$key,
					'HS256'
				);
				setcookie("token", $token, time() + 3600, "/", "", true, true);
				switch($data['role']) {
                    case 'admin':
                        header('location: /account/admin.php');
                        break;
                    case 'etudiant':
                        header('location: /account/étudiants.php');
                        break;
                    case 'enseignant':
                        header('location: /account/enseignants.php');
                        break;
                    default:
                        // Redirect to default page if role is not recognized
                        header('location: /account/unauthorized.php');
                }

			} else {
				$error = 'Wrong Password';
			}
		} else {
			$error = 'Wrong Email Address';
		}
	}
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN | PFE </title>

    <link rel="stylesheet" href="/assets/css/style.css">
    
</head>

<body>
<?php include($_SERVER["DOCUMENT_ROOT"]."/config/nav.php"); ?>

    <div class="login-box">
        <div class="login-header">LOGIN</div>

    <form  method="post">
        <div class="input-box">
            <input type="text" class="input-field" name="email" placeholder="email" autocomplete="off" required>
        </div>
        <div class="input-box">
            <input type="text" class="input-field" name="password" placeholder="password" autocomplete="off" required>
        </div>
    

        <div class="forgot">
            <section>
                <input type="checkbox"  id="check">
                <label for="check" >Remember me</label>
            </section>
            <section>
                <a href="#">Forgot password</a>
            </section></div>
        <div class="input-submit">
            <input type="submit" class="submit-btn" id="submit"  name="login"  value="Login" style="color:white;font-family: 'Spartan', sans-serif;font-size:35px"/>
            
            <div class="sign-up-link">
                <p>Don't have an account ? <a href="#">Sign Up</a></p>
            </div>
        </div>
    </form>
   <?php if($error !== '')
    				{
    					echo '<div style="color: red">'.$error.'</div>';}
    			?>	
        </div>
    </div>



</body>


</html>