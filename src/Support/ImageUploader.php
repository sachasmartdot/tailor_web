<?php
declare(strict_types=1);
namespace App\Support;
use RuntimeException;

class ImageUploader{
	/**
	* Enregistre une image envoyee par un formulaire.
	* Entrees : $file = $_FILES['nom_du_champ'], $subFolder = dossier sous
	public/ (avec / final),
	*
	$baseName = nom du fichier sans extension.
	* Sortie : le chemin à mettre dans <img src='...'>.
	*/
	public static function save(array $file, string $subFolder, string $baseName): string {
		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		throw new RuntimeException('Aucune image reçue.');
		}
		$mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
		$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
		
		if (!isset($allowed[$mime])) {
			throw new RuntimeException('Format refusé : jpg, png ou webp seulement.');
		}
		
		$dir = __DIR__ . '/../../public/' . $subFolder;
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			throw new RuntimeException('Impossible de créer le dossier : ' .$subFolder);
		}
		
		$name = $baseName . '.' . $allowed[$mime];
		if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
			throw new RuntimeException("Impossible d'enregistrer l'image.");
		}
		
		return $subFolder . $name;
	}
}
