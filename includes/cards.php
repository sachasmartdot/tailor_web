<?php
declare(strict_types=1);

/**
 * Small display helpers shared by the home page, the catalogue and the tailor pages.
 * Include after bootstrap.php, which provides e() and formatPrice().
 */

/** Prints one garment card. $tailorName is optional. */
function renderGarmentCard(array $model, string $tailorName = ''): void
{
    $id          = (string) ($model['id'] ?? '');
    $title       = (string) ($model['title'] ?? 'Untitled garment');
    $description = trim((string) ($model['description'] ?? ''));
    $image       = !empty($model['image']) ? (string) $model['image'] : 'assets/icones/default-avatar.jpeg';
    $price       = !empty($model['prix']) ? formatPrice((float) $model['prix']) : 'Price on request';
    $url         = 'article_detail.php?id=' . rawurlencode($id);
    ?>
    <article class="garment-card">
        <a class="garment-card__media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($image) ?>" alt="" loading="lazy">
        </a>
        <div class="garment-card__body">
            <?php if ($tailorName !== ''): ?>
                <p class="garment-card__tailor">By <?= e($tailorName) ?></p>
            <?php endif; ?>
            <h3 class="garment-card__title"><a href="<?= e($url) ?>"><?= e($title) ?></a></h3>
            <?php if ($description !== ''): ?>
                <p class="garment-card__text"><?= e($description) ?></p>
            <?php endif; ?>
            <p class="garment-card__price"><?= e($price) ?></p>
        </div>
    </article>
    <?php
}

/** Prints one tailor card. Expects the shape returned by CatalogueRepository::getTailors(). */
function renderAtelierCard(array $tailor): void
{
    $id          = (string) ($tailor['id'] ?? '');
    $name        = (string) ($tailor['pseudo'] ?? '') !== '' ? (string) $tailor['pseudo'] : 'Independent tailor';
    $description = trim((string) ($tailor['description'] ?? ''));
    $place       = trim((string) ($tailor['localisation'] ?? ''));
    $photo       = !empty($tailor['photo_profil']) ? (string) $tailor['photo_profil'] : 'assets/icones/default-avatar.jpeg';
    $url         = 'tailor_profile.php?uid=' . rawurlencode($id);

    if ($description === '') {
        $description = 'An independent tailor on Tailor Platform.';
    }
    ?>
    <article class="atelier-card">
        <a class="atelier-card__media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e($photo) ?>" alt="" loading="lazy">
        </a>
        <div class="atelier-card__body">
            <h3 class="atelier-card__name"><a href="<?= e($url) ?>"><?= e($name) ?></a></h3>
            <?php if ($place !== ''): ?>
                <p class="atelier-card__place"><?= e($place) ?></p>
            <?php endif; ?>
            <p class="atelier-card__text"><?= e($description) ?></p>
        </div>
    </article>
    <?php
}