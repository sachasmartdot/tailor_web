<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Nos tailleurs - Tailor Platform';

$tailorsQuery = [];
try {
    // Les tailleurs sont lus directement depuis Firestore.
    // La localisation est volontairement absente de cette page pour le moment.
    $tailorsQuery = $firebaseDb
        ->collection('users')
        ->where('type', '==', 'tailleur')
        ->documents();

    unset($_SESSION['firebaseTailor_error']);
} catch (\Throwable $e) {
    $_SESSION['firebaseTailor_error'] = 'Erreur de chargement des tailleurs : ' . $e->getMessage();
}

require __DIR__ . '/../includes/header.php';
?>

<main class="tailors-page">
    <section class="tailors-hero">
        <div class="tailors-container">
            <p class="tailors-eyebrow">TAILOR MARKETPLACE</p>
            <h1 class="tailors-title">Find your tailor.</h1>
            <p class="tailors-intro">
                Découvrez les tailleurs présents sur Tailor et trouvez le savoir-faire
                qui correspond à votre style.
            </p>

            <div class="tailors-search-wrap">
                <svg class="tailors-search-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"
                          fill="none" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round" />
                </svg>
                <input
                    id="tailorSearch"
                    type="search"
                    class="tailors-search"
                    placeholder="Search tailors, garments, or styles..."
                    autocomplete="off"
                    aria-label="Rechercher un tailleur"
                >
            </div>
        </div>
    </section>

    <section class="tailors-list-section">
        <div class="tailors-container">
            <?php if (isset($_SESSION['firebaseTailor_error'])): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-4">
                    <?= e($_SESSION['firebaseTailor_error']) ?>
                </div>
            <?php endif; ?>

            <div class="tailors-list-header">
                <div>
                    <p class="tailors-section-kicker">DISCOVER</p>
                    <h2 class="tailors-section-title">Our tailors</h2>
                </div>
                <span id="tailorCount" class="tailors-count"></span>
            </div>

            <div id="tailorsGrid" class="tailors-grid">
                <?php
                $hasTailors = false;
                foreach ($tailorsQuery as $document):
                    if (!$document->exists()) {
                        continue;
                    }

                    $hasTailors = true;
                    $tailor = $document->data();
                    $tailorUid = $document->id();

                    $photoProfil = !empty($tailor['photo_profil'])
                        ? (string) $tailor['photo_profil']
                        : 'assets/icones/default-avatar.jpeg';

                    $pseudo = (string) ($tailor['pseudo'] ?? 'Tailleur indépendant');
                    $description = trim((string) ($tailor['description'] ?? ''));
                    if ($description === '') {
                        $description = 'Créateur indépendant présent sur Tailor.';
                    }
                ?>
                    <article
                        class="tailor-showcase-card"
                        data-tailor-search="<?= e($pseudo . ' ' . $description) ?>"
                    >
                        <a
                            href="tailor_profile.php?uid=<?= e($tailorUid) ?>"
                            class="tailor-card-image-link"
                            aria-label="Voir le profil de <?= e($pseudo) ?>"
                        >
                            <div class="tailor-card-image-wrap">
                                <img
                                    src="<?= e($photoProfil) ?>"
                                    class="tailor-card-image"
                                    alt="Photo de <?= e($pseudo) ?>"
                                    loading="lazy"
                                >
                                <span class="tailor-card-badge">Tailor</span>
                            </div>
                        </a>

                        <div class="tailor-card-body">
                            <div class="tailor-card-heading">
                                <div>
                                    <p class="tailor-card-label">ATELIER</p>
                                    <h3 class="tailor-card-name"><?= e($pseudo) ?></h3>
                                </div>
                                <span class="tailor-card-mark" aria-hidden="true">T</span>
                            </div>

                            <p class="tailor-card-description">
                                <?= e($description) ?>
                            </p>

                            <a
                                href="tailor_profile.php?uid=<?= e($tailorUid) ?>"
                                class="tailor-card-button"
                            >
                                View profile
                                <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <?php if (!$hasTailors && !isset($_SESSION['firebaseTailor_error'])): ?>
                    <div class="tailors-empty">
                        <span class="tailors-empty-mark">T</span>
                        <h3>No tailor available yet.</h3>
                        <p>Les premiers ateliers apparaîtront ici dès leur inscription.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tailorsNoResult" class="tailors-empty tailors-empty-search" hidden>
                <span class="tailors-empty-mark">⌕</span>
                <h3>Aucun résultat.</h3>
                <p>Essayez avec un autre nom ou un autre mot-clé.</p>
            </div>
        </div>
    </section>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('tailorSearch');
        const cards = Array.from(document.querySelectorAll('.tailor-showcase-card'));
        const count = document.getElementById('tailorCount');
        const noResult = document.getElementById('tailorsNoResult');

        const update = function () {
            const query = input.value.trim().toLowerCase();
            let visible = 0;

            cards.forEach(function (card) {
                const text = (card.dataset.tailorSearch || '').toLowerCase();
                const matches = query === '' || text.includes(query);
                card.hidden = !matches;
                if (matches) visible++;
            });

            if (count) {
                count.textContent = visible + (visible > 1 ? ' tailleurs' : ' tailleur');
            }

            if (noResult) {
                noResult.hidden = visible !== 0;
            }
        };

        if (input) {
            input.addEventListener('input', update);
        }

        update();
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
