<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;
use App\Support\CartService;

$id = $_GET['id'] ?? '';

if ($id === '') {
    header('Location: catalogue.php');
    exit;
}

$model = CatalogueRepository::findById($id);

if ($model === null) {
    header('Location: catalogue.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Personnalisation disponible
|--------------------------------------------------------------------------
*/

$couleurs = [
    'Noir' => '#111111',
    'Blanc' => '#ffffff',
    'Beige' => '#d8c3a5',
    'Marron' => '#795548',
    'Bleu' => '#3f51b5',
    'Rouge' => '#b71c1c',
    'Vert' => '#4f6f52',
];

$tailles = [
    'S',
    'M',
    'L',
    'XL',
    'XXL',
];

$erreur = '';

/*
|--------------------------------------------------------------------------
| Ajout au panier
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $couleur = trim($_POST['couleur'] ?? '');
    $taille = trim($_POST['taille'] ?? '');

    if (!array_key_exists($couleur, $couleurs)) {
        $erreur = 'Veuillez choisir une couleur.';
    } elseif (!in_array($taille, $tailles, true)) {
        $erreur = 'Veuillez choisir une taille.';
    } else {

        CartService::add(
            $model,
            $couleur,
            $taille
        );

        header('Location: cart.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="assets/css/article_detail.css">

<div class="article-detail-page">

    <div class="article-detail-wrapper">

        <div class="article-detail-card">

            <div class="row g-0">

                <!-- Image -->
                <div class="col-lg-6">

                    <?php if (!empty($model['image'])): ?>

                        <img
                            src="<?= htmlspecialchars($model['image']) ?>"
                            alt="<?= htmlspecialchars($model['title'] ?? 'Modèle') ?>"
                            class="article-detail-image"
                        >

                    <?php else: ?>

                        <div
                            class="article-detail-image d-flex align-items-center justify-content-center"
                            style="background:#eee;"
                        >
                            <span>Aucune image</span>
                        </div>

                    <?php endif; ?>

                </div>

                <!-- Informations -->
                <div class="col-lg-6">

                    <div class="article-detail-content">

                        <div class="article-detail-label">
                            CUSTOMIZE YOUR MODEL
                        </div>

                        <h1 class="article-detail-title">
                            <?= htmlspecialchars($model['title'] ?? 'Modèle') ?>
                        </h1>

                        <?php if (!empty($model['description'])): ?>

                            <p class="article-detail-description">
                                <?= nl2br(
                                    htmlspecialchars($model['description'])
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <div class="article-detail-price">
                            <?= number_format(
                                (float) ($model['prix'] ?? 0),
                                0,
                                ',',
                                ' '
                            ) ?>
                            FCFA
                        </div>

                        <?php if ($erreur !== ''): ?>

                            <div class="alert alert-danger">
                                <?= htmlspecialchars($erreur) ?>
                            </div>

                        <?php endif; ?>

                        <form method="POST">

                            <!-- Couleur -->

                            <div class="customization-section">

                                <div class="customization-title">
                                    Couleur
                                </div>

                                <div class="color-options">

                                    <?php foreach ($couleurs as $nom => $code): ?>

                                        <label class="color-option">

                                            <input
                                                type="radio"
                                                name="couleur"
                                                value="<?= htmlspecialchars($nom) ?>"
                                                required
                                            >

                                            <span
                                                class="color-swatch"
                                                style="background-color: <?= htmlspecialchars($code) ?>;"
                                                title="<?= htmlspecialchars($nom) ?>"
                                            ></span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                            <!-- Taille -->

                            <div class="customization-section">

                                <div class="customization-title">
                                    Taille
                                </div>

                                <div class="size-options">

                                    <?php foreach ($tailles as $taille): ?>

                                        <label class="size-option">

                                            <input
                                                type="radio"
                                                name="taille"
                                                value="<?= htmlspecialchars($taille) ?>"
                                                required
                                            >

                                            <span class="size-button">
                                                <?= htmlspecialchars($taille) ?>
                                            </span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                            <!-- Ajouter au panier -->

                            <button
                                type="submit"
                                class="add-to-cart-button"
                            >
                                Ajouter au panier
                            </button>

                            <div class="customization-note">
                                Votre couleur et votre taille seront
                                conservées dans votre panier.
                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
