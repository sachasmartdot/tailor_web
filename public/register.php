<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';


//$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);


/*if(isset($_POST['login']) && isset($_POST['email'])){
    $tailor = new Tailor($db, 'user', $_POST['password'], 'SOA', 'Je suis tailleur', $_POST['email'], 'xxxxxxxxxxx', $_POST['type']);
    var_dump($tailor);

}
else{
    echo 'un soucis avec l\'inscription de l\'utilisateur';
}*/

/**
* controlleur d'inscription 
*
*/
use App\Model\User;


$postData = $_POST;

if (isset($_POST['signin'], $postData['terms'], $postData['email'], $postData['pseudo'], $postData['password'], $postData['telephone'])) {

	try {
		// les deux mots de passe doivent être identiques
		if ($postData['password'] !== ($postData['confirm-password'] ?? '')) {
			throw new \Exception('Les deux mots de passe ne sont pas identiques.');
		}

		$user = new User(
			$postData['pseudo'],
			$postData['password'],
			'',                          // localisation (pas encore dans le formulaire)
			'',                          // description (pas encore dans le formulaire)
			$postData['email'],
			$postData['telephone'],
			$postData['type'] ?? '',     // case "Je suis un tailleur" : absente si non cochée
			$_FILES['profilphoto'] ?? [],
			[]
		);

		$uid = $user->register();
		
		$redirectUrl = $_GET['redirect'] ?? 'index.php';
		
		header('Location: ' .$redirectUrl);
        exit();

		//echo "<div class='alert alert-success mt-3 container'>Inscription réussie (uid : " . e($uid) . ")</div>";
	}
	catch (\Throwable $e) {
		echo "<div class='alert alert-danger mt-3 container'>Inscription impossible : " . e($e->getMessage()) . "</div>";
	}
}

$pageTitle = 'Inscription - Tailor Platform';
require __DIR__ . '/../includes/header.php';

?>

		<!-- Inscription -->
		<section>
			<div id="signinModal">
				<div class="modal-dialog modal-dialog-centered">
					<div class="modal-content login-modal-content">

						<div class="modal-body">
							<p class="login-separator">
							   Informations generales
							</p>
						    <form class="login-form" method="POST" action="" enctype="multipart/form-data">

								<!-- champ cache pour la redirection vers la page d'appel apres connexion -->
								<input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) //url exact de la page actuelle ?>">

						        <!-- Email -->
						        <div class="login-field">
						            <label for="loginEmail">Email</label>

						            <div class="login-input-group">
						                <svg xmlns="http://www.w3.org/2000/svg"
						                     width="20"
						                     height="20"
						                     viewBox="0 0 32 32">
						                    <path d="m30.853 13.87a15 15 0 0 0-29.729 4.082
						                    15.1 15.1 0 0 0 12.876 12.918
						                    15.6 15.6 0 0 0 2.016.13
						                    14.85 14.85 0 0 0 7.715-2.145
						                    1 1 0 1 0-1.031-1.711
						                    13.007 13.007 0 1 1 5.458-6.529
						                    2.149 2.149 0 0 1-4.158-.759
						                    v-10.856a1 1 0 0 0-2 0v1.726
						                    a8 8 0 1 0 .2 10.325
						                    4.135 4.135 0 0 0 7.83.274
						                    15.2 15.2 0 0 0 .823-7.455zm-14.853
						                    8.13a6 6 0 1 1 6-6 6.006 6.006 0 0 1-6 6z">
						                    </path>
						                </svg>

						                <input
						                    id="loginEmail"
						                    name="email"
						                    type="email"
						                    placeholder="Entrez votre email"
						                    required>
						            </div>
						        </div>
						       
						        <!-- pseudo -->
						        <div class="login-field">
						            <label for="loginEmail">Pseudo</label>

						            <div class="login-input-group">

						                <input
						                    id="loginEmail"
						                    name="pseudo"
						                    type="text"
						                    placeholder="Entrez votre pseudo"
						                    required>
						            </div>
						        </div>
						        

						        <!-- Password -->
						        <div class="login-field">
						            <label for="loginPassword">Mot de passe</label>

						            <div class="login-input-group">
						                <svg xmlns="http://www.w3.org/2000/svg"
						                     width="20"
						                     height="20"
						                     viewBox="-64 0 512 512">
						                    <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562
						                    21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562
						                    -21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031
						                    7.1875 16 16 16h288c8.8125 0 16-7.167969-7.1875 16-16v-224c0-8.832031
						                    -7.1875-16-16-16zm0 0"></path>
						                    <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96
						                    43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375
						                    57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0">
						                    </path>
						                </svg>

						                <input
						                    id="loginPassword"
						                    name="password"
						                    type="password"
						                    placeholder="Entrez votre mot de passe"
						                    required>
						            </div>
						        </div>

						        <!-- Confirm Password -->
						        <div class="login-field">
						            <label for="loginPassword">Confirmer le mot de passe</label>

						            <div class="login-input-group">
						                <svg xmlns="http://www.w3.org/2000/svg"
						                     width="20"
						                     height="20"
						                     viewBox="-64 0 512 512">
						                    <path d="m336 512h-288c-26.453125 0-48-21.523438-48-48v-224c0-26.476562
						                    21.546875-48 48-48h288c26.453125 0 48 21.523438 48 48v224c0 26.476562
						                    -21.546875 48-48 48zm-288-288c-8.8125 0-16 7.167969-16 16v224c0 8.832031
						                    7.1875 16 16 16h288c8.8125 0 16-7.167969-7.1875 16-16v-224c0-8.832031
						                    -7.1875-16-16-16zm0 0"></path>
						                    <path d="m304 224c-8.832031 0-16-7.167969-16-16v-80c0-52.929688-43.070312-96-96-96s-96
						                    43.070312-96 96v80c0 8.832031-7.167969 16-16 16s-16-7.167969-16-16v-80c0-70.59375
						                    57.40625-128 128-128s128 57.40625 128 128v80c0 8.832031-7.167969 16-16 16zm0 0">
						                    </path>
						                </svg>

						                <input
						                    id="loginPassword"
						                    name="confirm-password"
						                    type="password"
						                    placeholder="Confirmez votre mot de passe"
						                    required>
						            </div>
						        </div>
						        <p class="login-separator">
						            Informations complementaire
						        </p>
						        <!-- Numero de telephone -->
								<div class="row mb-3">
									<label class="col-sm-3 col-form-label" for="telephone">
										Numero de telephone:
									</label>
									<div class="col-sm-9">
										<div class="input-group">
											<span class="input-group-text">+237</span>
											<input 
												type="tel"
												class="form-control"
												id="telephone"
												name="telephone"
												placeholder="6XXXXXXXX"
												pattern="[0-9]{9}"
												title="Veuillez entrer un numéro valide de 9 chiffres"
												required
											/>
										</div>
										<small class="form-text text-muted">Exemple : 690000000</small>
									</div>
								</div>
								
								<!-- Upload image -->
								<div class="row mb-3">
									<label class="col-sm-3 col-form-label"for="uploadImage">
										Importer une photo de profil:
									</label>
									<div class="col-sm-9">
										<input name="profilphoto" type="file" class="form-control" id="uploadImage" />
									</div>
								</div>
								
								
								
						        <!-- Options -->
						        <div class="login-options">
						            <div>
						                <input
						                    type="checkbox"
						                    id="rememberMe"
						                    name="type">

						                <label for="rememberMe">
						                    Je suis un tailleur
						                </label>
						            </div>

						        </div>

								<!-- Terms and conditions -->
								<div class="row mb-3">
									<div class="col-sm-9 offset-sm-3">
										<div class="form-check">
											<input class="form-check-input" type="checkbox" id="checkAgree" value="agree" name="terms"/>
											<label class="form-check-label" for="checkAgree">
												I agree to the <a href="#">Terms and Conditions</a>
											</label>
										</div>
									</div>
								</div>

						        <!-- Connexion -->
						        <button
						            type="submit"
						            name="signin"
						            class="login-submit">
						            S'inscrire
						        </button>

						        <!-- <p class="login-separator">
						            Ou continuer avec
						        </p> -->

						        <!-- Google / Apple -->
						        <!-- <div class="login-social"> -->

						            <!-- <button type="button" class="social-btn google-btn"> -->
						                <!-- SVG Google -->
						                <!-- Google -->
						            <!-- </button> -->

						            <!-- <button type="button" class="social-btn apple-btn"> -->
						                <!-- SVG Apple -->
						                <!-- Apple -->
						            <!-- </button> -->

						        </div>

						    </form>

						</div>
					</div>
				</div>
			</div>

			<!-- <main class="container py-4"> -->
		</section>
	</body>
	<?php 
		require __DIR__ . '/../includes/footer.php'; 
	?>
</html>

