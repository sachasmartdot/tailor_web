<?php
declare(strict_types=1);

namespace App\Data;

use Google\Cloud\Firestore\FieldValue;

class CatalogueRepository{
/** Tous les modèles. Sortie : liste de tableaux (clé 'id' ajoutée). */
	public static function getAll(): array {
		global $firebaseDb;
		$models = [];
		foreach ($firebaseDb->collection('models')->documents() as $snap) {
			if ($snap->exists()) {
			$models[] = ['id' => $snap->id()] + $snap->data();
			}
		}
		return $models;
		
	}
	
	/** Les modèles d'un tailleur. Entrée : son uid. */
	public static function getByTailor(string $tailorUid): array {
		global $firebaseDb;
		$models = [];
		$query = $firebaseDb->collection('models')->where('uid', '=', $tailorUid);
		
		foreach ($query->documents() as $snap) {
			if ($snap->exists()) {
				$models[] = ['id' => $snap->id()] + $snap->data();
			}
		}
		
		return $models;
		
	}
	
	
	/** Un modèle par son id. Sortie : un tableau, ou null s'il n'existe pas.
	*/
	public static function findById(string $id): ?array {
	
		global $firebaseDb;
		$snap = $firebaseDb->collection('models')->document($id)->snapshot();
		
		return $snap->exists() ? ['id' => $snap->id()] + $snap->data() : null;
	}
	
	/** Ajoute un modèle. Sortie : l'id du document créé. */
	public static function add(string $tailorUid, string $title, string $description, float $prix, string $image = ''): string {
	
		global $firebaseDb;
		$ref = $firebaseDb->collection('models')->add([ 
			'uid' => $tailorUid,
			'title' => $title,
			'description' => $description,
			'prix' => $prix,
			'image' => $image,
			'created_at'=> FieldValue::serverTimestamp(),
		]);
		
		return $ref->id();
	}
}
