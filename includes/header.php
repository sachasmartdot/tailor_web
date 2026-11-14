<?php
declare(strict_types=1);

use App\Support\CartService;

// Sign in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'], $_POST['email'], $_POST['password'])) {
    try {
        // Check the email and password with Firebase Auth
        $signInResult = $firebaseAuth->signInWithEmailAndPassword($_POST['email'], $_POST['password']);
        $uid = $signInResult->firebaseUserId();

        // Load the matching profile from Firestore
        $fiche = $firebaseDb->collection('users')->document($uid)->snapshot();
        $profil = $fiche->exists() ? $fiche->data() : [];

        // Start the session
        $_SESSION['user'] = [
            'uid' => $uid,
            'email' => $_POST['email'],
            'pseudo' => $profil['pseudo'] ?? '',
            'type' => $profil['type'] ?? 'client',
        ];

        // Where to send the person afterwards
        if (str_contains($_SERVER['REQUEST_URI'], 'register')) {
            $redirectUrl = $_GET['redirect'] ?? 'index.php';
        } else {
            $redirectUrl = $_POST['redirect'] ?? 'index.php';
        }

        header('Location: ' . $redirectUrl);
        exit();
    } catch (\Exception $e) {
        $_SESSION['login_error'] = 'Incorrect email or password.';
    }
}

$cartCount = CartService::count();

// Which page we are on, to highlight the matching menu link
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$navLinks = [
    ['catalogue.php', 'Garments', ['catalogue.php', 'article_detail.php']],
    ['tailors.php', 'Tailors', ['tailors.php', 'tailor_profile.php']],
];

$userType = (string) ($_SESSION['user']['type'] ?? '');
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
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

<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">

            <a class="navbar-brand" href="index.php">
                <span class="brand-logo">T</span>
                <span>Tailor</span>
            </a>

            <!-- Cart, account and the menu button stay visible on every screen size -->
            <div class="site-nav__actions ms-auto ms-lg-0 order-lg-2">
                <a href="cart.php" class="btn btn-outline-cart position-relative">
                    Cart
                    <?php if ($cartCount > 0): ?>
                        <span class="badge-cart"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['user'])): ?>
                    <div class="dropdown">
                        <a href="#" class="site-nav__account" data-bs-toggle="dropdown" aria-expanded="false" aria-label="My account">
                            <img src="assets/icones/inlogou.png" height="28" alt="">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small"><?= e((string) $_SESSION['user']['email']) ?></span></li>
                            <?php if ($userType === 'tailleur'): ?>
                                <li><a href="tailor_profile.php?uid=<?= e(rawurlencode((string) $_SESSION['user']['uid'])) ?>" class="dropdown-item">My profile</a></li>
                                <li><a href="tailor_orders.php" class="dropdown-item">Orders received</a></li>
                                <li><a href="client_orders.php" class="dropdown-item">My purchases</a></li>
                            <?php else: ?>
                                <li><a href="client_orders.php" class="dropdown-item">My orders</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#loginModal">
                        Sign in
                    </button>
                <?php endif; ?>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                        aria-controls="mainNav" aria-expanded="false" aria-label="Open the menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-1" id="mainNav">
                <ul class="navbar-nav site-nav__links">
                    <?php foreach ($navLinks as [$href, $label, $matches]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= in_array($currentPage, $matches, true) ? ' active' : '' ?>"
                               href="<?= e($href) ?>"<?= in_array($currentPage, $matches, true) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>
    </nav>
</header>

<!-- Sign in window -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content login-modal-content">

            <button type="button" class="btn-close login-close" data-bs-dismiss="modal" aria-label="Close"></button>

            <div class="modal-body">
                <h2 class="visually-hidden" id="loginModalLabel">Sign in</h2>

                <?php if ($loginError !== null): ?>
                    <div class="alert alert-danger py-2" role="alert"><?= e((string) $loginError) ?></div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="">

                    <!-- Brings the person back to the page they were on -->
                    <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">

                    <div class="login-field">
                        <label for="loginEmail">Email</label>
                        <div class="login-input-group">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="m3 7 9 6 9-6"></path>
                            </svg>
                            <input id="loginEmail" name="email" type="email" placeholder="you@example.com" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="loginPassword">Password</label>
                        <div class="login-input-group">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                                <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input id="loginPassword" name="password" type="password" placeholder="Your password" autocomplete="current-password" required>
                        </div>
                    </div>

                    <button type="submit" name="login" class="login-submit">Sign in</button>

                    <p class="login-register">
                        New here?
                        <a href="register.php?redirect=<?= e(rawurlencode($_SERVER['REQUEST_URI'])) ?>">Create an account</a>
                    </p>

                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($loginError !== null): ?>
<script>
    // A sign-in attempt just failed: open the window again so the message is seen
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('loginModal');
        if (modal && window.bootstrap) {
            new bootstrap.Modal(modal).show();
        }
    });
</script>
<?php endif; ?>

<main id="main">