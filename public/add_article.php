<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Data\CatalogueRepository;
use App\Support\ImageUploader;
use App\Support\SizeGuide;

// Only tailors can add garments. Everyone else goes back to the home page.
$user = requireRole('tailleur');

$errors = [];
$title = $description = $priceRaw = $category = $garmentType = $sizeNote = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priceRaw    = trim((string) ($_POST['price'] ?? ''));
    $category    = trim((string) ($_POST['category'] ?? ''));
    $garmentType = trim((string) ($_POST['garment_type'] ?? ''));
    $sizeNote    = trim((string) ($_POST['size_note'] ?? ''));

    if ($title === '') {
        $errors[] = 'Please give your garment a title.';
    }
    if ($description === '') {
        $errors[] = 'Please add a short description.';
    }
    if (!is_numeric($priceRaw) || (float) $priceRaw <= 0) {
        $errors[] = 'The price must be a number greater than 0.';
    }
    if ($category !== '' && !isset(SizeGuide::CATEGORIES[$category])) {
        $errors[] = 'Please choose a category from the list.';
    }
    if ($garmentType !== '' && !isset(SizeGuide::GARMENT_TYPES[$garmentType])) {
        $errors[] = 'Please choose a garment type from the list.';
    }
    if (mb_strlen($sizeNote) > 300) {
        $errors[] = 'Sizing notes can be up to 300 characters.';
    }

    if ($errors === []) {
        try {
            // 1. The photo is optional
            $imagePath = '';

            if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $imagePath = ImageUploader::save($_FILES['image'], 'assets/users/' . $user['uid'] . '/models/', 'm' . time());
            }

            // 2. Save the garment in Firestore
            CatalogueRepository::add(
                $user['uid'],
                $title,
                $description,
                (float) $priceRaw,
                $imagePath,
                $category,
                $garmentType,
                $sizeNote
            );
            header('Location: tailor_profile.php?uid=' . urlencode($user['uid']));
            exit;
        } catch (\Throwable $e) {
            $errors[] = 'We could not save your garment: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add a garment - Tailor Platform';
require __DIR__ . '/../includes/header.php';
?>

<div class="page page--narrow">
    <a href="tailor_profile.php?uid=<?= e(urlencode($user['uid'])) ?>" class="back-link">&larr; Back to my profile</a>

    <h1 class="page-title">Add a garment</h1>
    <p class="page-lead">Share a piece from your workshop. Customers will see it in the catalogue and on your profile.</p>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form-card" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= e($title) ?>" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4" required><?= e($description) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Price (FCFA)</label>
            <input type="number" step="1" min="0" inputmode="numeric" class="form-control" id="price" name="price" value="<?= e($priceRaw) ?>" required>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <label for="category" class="form-label">Category <span class="form-optional">(optional)</span></label>
                <select class="form-select" id="category" name="category">
                    <option value="">Not specified</option>
                    <?php foreach (SizeGuide::CATEGORIES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label for="garment_type" class="form-label">Garment type <span class="form-optional">(optional)</span></label>
                <select class="form-select" id="garment_type" name="garment_type">
                    <option value="">Not specified</option>
                    <?php foreach (SizeGuide::GARMENT_TYPES as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $garmentType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <p class="form-text mb-0">
                    The category and type help us show customers a sensible size conversion (France, Europe, US, UK).
                    You can leave them empty.
                </p>
            </div>
        </div>

        <div class="mb-3">
            <label for="size_note" class="form-label">Sizing notes <span class="form-optional">(optional)</span></label>
            <textarea class="form-control" id="size_note" name="size_note" rows="3" maxlength="300"
                      placeholder="For example: cut slim, size up if you are between sizes. Larger sizes available on request."><?= e($sizeNote) ?></textarea>
            <p class="form-text mb-0">Anything customers should know about how this piece fits. Up to 300 characters.</p>
        </div>

        <div class="mb-4">
            <label for="image" class="form-label">Photo</label>
            <input type="file" class="form-control" id="image" name="image" accept="image/*">
            <p class="form-text mb-0">JPG, PNG or WebP. A clear, well-lit photo works best.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save garment</button>
            <a href="tailor_profile.php?uid=<?= e(urlencode($user['uid'])) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>