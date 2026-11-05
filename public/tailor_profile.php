<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$guest = isset($_SESSION['user']['type'])
    ? ($_SESSION['user']['type'] === 'tailleur' ? 'tailleur' : 'client')
    : 'visiteur';

$pageTitle = 'Profil ' . $guest . ' - Tailor Platform';

$tailorUid = (string) ($_GET['uid'] ?? '');

$tailor = null;
$models = [];

/*
|--------------------------------------------------------------------------
| Récupération du profil du tailleur
|--------------------------------------------------------------------------
*/

try {
    if ($tailorUid !== '') {
        $document = $firebaseDb
            ->collection('users')
            ->document($tailorUid)
            ->snapshot();

        if ($document->exists()) {
            $tailor = $document->data();
            unset($_SESSION['firebaseTailor_error']);
        } else {
            $_SESSION['firebaseTailor_error'] = 'Ce tailleur n\'existe pas.';
        }
    } else {
        $_SESSION['firebaseTailor_error'] =
            'Aucun identifiant de tailleur spécifié.';
    }
} catch (\Throwable $e) {
    $_SESSION['firebaseTailor_error'] =
        'Erreur de chargement du tailleur : ' . $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| Récupération des modèles du tailleur
|--------------------------------------------------------------------------
*/

try {
    if ($tailorUid !== '') {
        $models = $firebaseDb
            ->collection('models')
            ->where('uid', '==', $tailorUid)
            ->documents();
    }
} catch (\Throwable $e) {
    $_SESSION['firebaseTailor_error'] =
        'Erreur de chargement des modèles : ' . $e->getMessage();
}

require __DIR__ . '/../includes/header.php';
?>

<main class="tailor-profile-page">

    <?php if (isset($_SESSION['firebaseTailor_error'])): ?>

        <div class="tailor-profile-container">
            <div class="tailor-profile-alert">
                <?= e($_SESSION['firebaseTailor_error']) ?>
            </div>
        </div>

    <?php endif; ?>


    <?php if ($tailor): ?>

        <?php
        $pseudo = (string) ($tailor['pseudo'] ?? 'Tailleur indépendant');

        $description = trim(
            (string) ($tailor['description'] ?? '')
        );

        if ($description === '') {
            $description =
                'Créateur indépendant présent sur Tailor.';
        }

        $photoProfil = !empty($tailor['photo_profil'])
            ? (string) $tailor['photo_profil']
            : 'assets/icones/default-avatar.jpeg';
        ?>


        <!-- =========================================================
             RETOUR
        ========================================================== -->

        <div class="tailor-profile-container">
            <a
                href="tailors.php"
                class="tailor-profile-back"
            >
                <span aria-hidden="true">&larr;</span>
                Back to tailors
            </a>
        </div>


        <!-- =========================================================
             HERO / PROFIL DU TAILLEUR
        ========================================================== -->

        <section class="tailor-profile-hero">

            <div class="tailor-profile-container">

                <div class="tailor-profile-intro">

                    <div class="tailor-profile-photo-wrap">

                        <img
                            src="<?= e($photoProfil) ?>"
                            alt="Photo de <?= e($pseudo) ?>"
                            class="tailor-profile-photo"
                        >

                        <span class="tailor-profile-photo-badge">
                            TAILOR
                        </span>

                    </div>


                    <div class="tailor-profile-info">

                        <p class="tailor-profile-eyebrow">
                            TAILOR ATELIER
                        </p>

                        <h1 class="tailor-profile-name">
                            <?= e($pseudo) ?>
                        </h1>

                        <p class="tailor-profile-description">
                            <?= e($description) ?>
                        </p>


                        <div class="tailor-profile-actions">

                            <a
                                href="#collection"
                                class="tailor-profile-primary-button"
                            >
                                View collection
                                <span aria-hidden="true">&rarr;</span>
                            </a>


                            <?php if (
                                isset($_SESSION['user']) &&
                                $_SESSION['user']['uid'] === $tailorUid
                            ): ?>

                                <a
                                    href="add_article.php"
                                    class="tailor-profile-secondary-button"
                                >
                                    Add a model
                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =========================================================
             COLLECTION
        ========================================================== -->

        <section
            id="collection"
            class="tailor-profile-collection"
        >

            <div class="tailor-profile-container">

                <div class="tailor-profile-section-header">

                    <div>

                        <p class="tailor-profile-section-kicker">
                            THE COLLECTION
                        </p>

                        <h2 class="tailor-profile-section-title">
                            <?= e($pseudo) ?>'s work
                        </h2>

                    </div>

                    <p class="tailor-profile-section-count">
                        <?php
                        $modelCount = 0;

                        foreach ($models as $modelDoc) {
                            if ($modelDoc->exists()) {
                                $modelCount++;
                            }
                        }

                        echo $modelCount;
                        ?>

                        <?= $modelCount > 1 ? ' models' : ' model' ?>
                    </p>

                </div>


                <div class="tailor-profile-grid">

                    <?php
                    $hasModels = false;

                    foreach ($models as $modelDoc):

                        if (!$modelDoc->exists()) {
                            continue;
                        }

                        $hasModels = true;

                        $model = $modelDoc->data();

                        $modelId = $modelDoc->id();

                        $modelImage = !empty($model['image'])
                            ? (string) $model['image']
                            : 'assets/icones/default-avatar.jpeg';

                        $modelTitle = (string) (
                            $model['title'] ?? 'Modèle indépendant'
                        );

                        $modelDescription = trim(
                            (string) ($model['description'] ?? '')
                        );

                        $modelPrice = !empty($model['prix'])
                            ? formatPrice((float) $model['prix'])
                            : 'Prix sur demande';
                    ?>

                        <article class="tailor-model-card">

                            <a
                                href="article_detail.php?id=<?= e($modelId) ?>"
                                class="tailor-model-image-link"
                            >

                                <div class="tailor-model-image-wrap">

                                    <img
                                        src="<?= e($modelImage) ?>"
                                        alt="<?= e($modelTitle) ?>"
                                        class="tailor-model-image"
                                        loading="lazy"
                                    >

                                    <span class="tailor-model-view">
                                        View
                                    </span>

                                </div>

                            </a>


                            <div class="tailor-model-content">

                                <div class="tailor-model-top">

                                    <div>

                                        <p class="tailor-model-label">
                                            MADE TO MEASURE
                                        </p>

                                        <h3 class="tailor-model-title">
                                            <?= e($modelTitle) ?>
                                        </h3>

                                    </div>

                                    <span class="tailor-model-mark">
                                        T
                                    </span>

                                </div>


                                <?php if ($modelDescription !== ''): ?>

                                    <p class="tailor-model-description">
                                        <?= e($modelDescription) ?>
                                    </p>

                                <?php endif; ?>


                                <div class="tailor-model-footer">

                                    <span class="tailor-model-price">
                                        <?= e($modelPrice) ?>
                                    </span>

                                    <a
                                        href="article_detail.php?id=<?= e($modelId) ?>"
                                        class="tailor-model-button"
                                    >
                                        View model
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>


                    <?php if (!$hasModels): ?>

                        <div class="tailor-profile-empty">

                            <span class="tailor-profile-empty-mark">
                                T
                            </span>

                            <h3>
                                No models yet.
                            </h3>

                            <p>
                                Ce tailleur n'a pas encore ajouté de modèles
                                à sa collection.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </section>


    <?php else: ?>

        <!-- =========================================================
             PROFIL INTROUVABLE
        ========================================================== -->

        <section class="tailor-profile-not-found">

            <div class="tailor-profile-container">

                <span class="tailor-profile-empty-mark">
                    T
                </span>

                <h1>
                    Tailor not found
                </h1>

                <p>
                    Impossible de charger le profil de ce tailleur.
                </p>

                <a
                    href="tailors.php"
                    class="tailor-profile-primary-button"
                >
                    Back to tailors
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>


<?php require __DIR__ . '/../includes/footer.php'; ?>
