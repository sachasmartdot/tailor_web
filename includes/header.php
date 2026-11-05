<?php
declare(strict_types=1);

use App\Support\CartService;

// connexion
// $messageConnexion = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['email'], $_POST['password'])) {
    try {
        // Auth firebase
        $signInResult = $firebaseAuth->signInWithEmailAndPassword($_POST['email'], $_POST['password']);
        $uid = $signInResult->firebaseUserId();
        
        // on recupere le doc firestore correspondant
        $fiche = $firebaseDb->collection('users')->document($uid)->snapshot();
        $profil = $fiche->exists() ? $fiche->data() : [];
        
        // on cree la session
        $_SESSION['user'] = [
            'uid' => $uid,
            'email' => $_POST['email'],
            'pseudo' => $profil['pseudo'] ?? '',
            'type' => $profil['type'] ?? 'client',
        ];
        
        // url de redirection
        if(str_contains($_SERVER['REQUEST_URI'], 'register')){
        	$redirectUrl = $_GET['redirect'] ?? 'index.php';
        }
        else{
        	$redirectUrl = $_POST['redirect'] ?? 'index.php';
		}

        // redirection

        // $messageConnexion = "<div class='alert alert-success mt-3 container'>[FIREBASE AUTH OK] Connecté avec succès ! UID : <strong>$uid</strong></div>";
        header('Location: ' .$redirectUrl);
        exit();        
    } catch (\Exception $e) {
        $_SESSION['login_error'] = "Email ou password incorrect.";
        // $messageConnexion = "<div class='alert alert-danger mt-3 container'>[ERREUR de connexion] Email ou mot de passe incorrect.</div>";
    }
}

$cartCount = CartService::count();
?>

<!-- NOTE : -->
 <!-- les echo intempestifs -->

<!doctype html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($pageTitle ?? 'Tailor Platform') ?></title>
        <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/home_searchbar.css">
        <link rel="stylesheet" href="assets/css/loginmodal.css">
        <link rel="stylesheet" href="assets/css/tailors.css">
        <link rel="stylesheet" href="assets/css/tailor_profile.css">
        <link rel="stylesheet" href="assets/css/article_detail.css">
		<link rel="stylesheet" href="assets/css/cart.css">
		<link rel="stylesheet" href="assets/css/tailor_orders.css">
		<link rel="stylesheet" href="assets/css/client_orders.css">
    </head>
<body>

<header class="site-header position-relative z-1">
    <nav class="navbar navbar-expand-lg py-3">
        <div class="container d-flex justify-content-between align-items-center">
            
            <!-- 1. Logo à gauche (agit aussi comme onglet Home) -->
            <a class="navbar-brand d-flex align-items-center gap-2 active" href="index.php" >
                <span class="brand-logo">T</span>
                <span>Tailor</span>
            </a>
            <!-- 3. Actions à droite (Panier + Connexion/Profil) -->
            <div class="d-flex align-items-center gap-3">
                <a href="cart.php" class="btn btn-outline-cart position-relative">
                    Panier
                    <?php if ($cartCount > 0): ?>
                    <span class="badge-cart"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['user'])): ?>
                    <div class="dropdown">
                        <a href="#" class="navbar-brand m-0" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="assets/icones/inlogou.png" height="28" alt="Profile">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?= e($_SESSION['user']['email']) ?></span></li>
                            <!-- si c'est un tailleur -->
                            <?php if(($_SESSION['user']['type'] ?? '') === 'tailleur'): ?>
                                <li>
                                    <a href="tailor_profile.php?uid=<?= e($_SESSION['user']['uid']) ?>">Mon profil</a>
                                </li>
                                <li>
                                    <a href="tailor_orders.php" class="dropdown-item">Mes commandes</a>
                                </li>
                            <?php 
                            // si c'est un client
                            else:
                            	
                            	echo '<li>
                                		<a href="client_orders.php" class="dropdown-item">Mes commandes</a>
                            		</li>';
                            	
                            endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Se déconnecter</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#loginModal">
                        Connexion
                    </button>
                <?php endif; ?>
            </div>

        </div>
    </nav>
</header>

<!-- ... -->
<!-- Modal Connexion -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content login-modal-content">

            <!-- Bouton fermer -->
            <button
                type="button"
                class="btn-close login-close"
                data-bs-dismiss="modal"
                aria-label="Fermer">
            </button>

            <div class="modal-body">
                <?php if(isset($_SESSION['login_error'])): ?>

                    <div class="alert alert-danger py-2">
                        <?= e($_SESSION['login_error']) ?>
                    </div>
                <?php endif ?>

                <form class="login-form" method="POST" action="">

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

                        <a href="forgot-password.php">
                            Mot de passe oublié ?
                        </a>
                    </div>

                    <!-- Connexion -->
                    <button
                        type="submit"
                        name="login"
                        class="login-submit">
                        Se connecter
                    </button>

                    <!-- Inscription -->
                    <p class="login-register">
                        Vous n'avez pas de compte ?
                        <a href="register.php?redirect=<?= e($_SERVER['REQUEST_URI'])?>">
                            Créer un compte
                        </a>
                    </p>

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
