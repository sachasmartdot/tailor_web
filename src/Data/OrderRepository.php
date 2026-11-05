<?php
declare(strict_types=1);
namespace App\Data;

use Google\Cloud\Firestore\FieldValue;

class OrderRepository {
	/**
	 * Crée UNE commande par tailleur, AVANT le paiement. Elles restent invisibles (client et
	 * tailleur) tant que paiement_statut n'est pas SUCCESSFUL.
	 * $paiementStatut ne change que pour les commandes de test ('SUCCESSFUL').
	 */
	public static function createPendingFromCart(array $client, array $items, string $paiementRef = '', string $paiementStatut = 'PENDING'): array {
		global $firebaseDb;

		$parTailleur = [];
		foreach ($items as $item) {
			$tailorUid = (string) ($item['tailor_uid'] ?? '');
			if ($tailorUid === '') {
				throw new \RuntimeException('Un article du panier n\'a pas de tailleur associé.');
			}
			$parTailleur[$tailorUid][] = $item;
		}

		$ids = [];
		foreach ($parTailleur as $tailorUid => $articles) {
			$ref = $firebaseDb->collection('orders')->add([
				'client_uid' => $client['uid'],
				'client_pseudo' => $client['pseudo'] ?? '',
				'tailor_uid' => $tailorUid,
				'items' => $articles,
				'total' => array_sum(array_column($articles, 'prix')),
				'statut' => 'en_attente',
				'paiement_statut' => $paiementStatut,
				'paiement_ref' => $paiementRef,
				'paiement_txnid' => '',
				'paiement_reason' => '',
				'suivi_note' => '',
				'suivi_image' => '',
				'recu' => false,
				'retrait' => '',
				'created_at' => FieldValue::serverTimestamp(),
			]);
			$ids[] = $ref->id();
		}

		return $ids;
	}

	/** Vrai si la commande est payée (ou ancienne, sans suivi de paiement). Les autres restent invisibles. */
	public static function estPayee(array $order): bool {
		$s = strtoupper((string) ($order['paiement_statut'] ?? ''));
		return $s === '' || $s === 'SUCCESSFUL';
	}
	
	/** Commandes reçues par un tailleur, les plus récentes d'abord. Entrée :
	son uid. */
	public static function getByTailor(string $tailorUid): array {
		return self::getBy('tailor_uid', $tailorUid);
	}
	
	/** Commandes passées par un client, les plus récentes d'abord. */
	public static function getByClient(string $clientUid): array {
		return self::getBy('client_uid', $clientUid);
	}
	
	/** Une commande par son id, ou null si elle n'existe pas. */
	public static function findById(string $id): ?array {
		global $firebaseDb;
		
		$snap = $firebaseDb->collection('orders')->document($id)->snapshot();
		
		return $snap->exists() ? ['id' => $snap->id()] + $snap->data() : null;
	}
	
	/** Le tailleur met à jour l'avancement : statut, message et photo (facultative). */
	public static function updateSuivi(string $id, string $statut, string $note, string $image = ''): void {
		global $firebaseDb;
		
		$donnees = ['statut' => $statut, 'suivi_note' => $note];
		if ($image !== '') {
			$donnees['suivi_image'] = $image;
		}
		
		$firebaseDb->collection('orders')->document($id)->set($donnees, ['merge' => true]);
	}
	
	/**
	* Le client confirme qu'il a reçu sa commande. Seulement si le tailleur l'a
	marquée terminée. Sortie : true si c'est enregistré.
	*/
	public static function confirmReception(string $id, string $clientUid): bool {
		global $firebaseDb;
		
		$order = self::findById($id);
		if ($order === null || !self::estPayee($order) || ($order['client_uid'] ?? '') !== $clientUid || ($order['statut'] ?? '') !== 'termine' || !empty($order['recu'])) {
			return false;
		}
		
		$firebaseDb->collection('orders')->document($id)->set(['recu' => true, 'recu_at' => FieldValue::serverTimestamp()], ['merge' => true]);
		return true;
	}
	
	/**
	* Réserve le retrait d'une commande. Fait dans une transaction pour qu'un
	double clic ne déclenche pas deux retraits.
	* Sortie : la commande si le retrait peut partir, null sinon.
	*/
	public static function lockRetrait(string $id, string $tailorUid, string $operateur): ?array {
		global $firebaseDb;
		
		$ref = $firebaseDb->collection('orders')->document($id);
		
		return $firebaseDb->runTransaction(function ($transaction) use ($ref, $tailorUid, $operateur) {
			$snap = $transaction->snapshot($ref);
			if (!$snap->exists()) {
				return null;
			}
			
			$order = ['id' => $snap->id()] + $snap->data();
			
			// il faut : sa commande, validée par le client, pas déjà retirée
			if (($order['tailor_uid'] ?? '') !== $tailorUid || empty($order['recu']) || ($order['retrait'] ?? '') !== '' || !self::estPayee($order)) {
				return null;
			}
			
			$transaction->update($ref, [
				['path' => 'retrait', 'value' => 'en_cours'],
				['path' => 'retrait_operateur', 'value' => $operateur],
			]);
			
			return $order;
		});
	}
	
	/** Enregistre le résultat d'un retrait : 'effectue', 'en_cours' ou '' (échec, on peut réessayer). */
	public static function setRetrait(string $id, string $etat, string $ref = ''): void {
		global $firebaseDb;
		
		$donnees = ['retrait' => $etat];
		if ($ref !== '') {
			$donnees['retrait_ref'] = $ref;
		}
		if ($etat === 'effectue') {
			$donnees['retrait_at'] = FieldValue::serverTimestamp();
		}
		
		$firebaseDb->collection('orders')->document($id)->set($donnees, ['merge' => true]);
	}
	
	/**
	* Soldes d'un tailleur à partir de ses commandes.
	* retirable : commandes validées par le client et pas encore retirées
	* en_cours : commandes payées mais pas encore validées par le client
	* retire : commandes dont le retrait est fait ou en cours
	*/
	public static function soldes(array $orders): array {
		$soldes = ['retirable' => 0.0, 'en_cours' => 0.0, 'retire' => 0.0];
		
		foreach ($orders as $o) {
			if (!self::estPayee($o)) {
				continue;
			}

			$total = (float) ($o['total'] ?? 0);
			
			if (($o['retrait'] ?? '') !== '') {
				$soldes['retire'] += $total;
			}
			elseif (!empty($o['recu'])) {
				$soldes['retirable'] += $total;
			}
			else {
				$soldes['en_cours'] += $total;
			}
		}
		
		return $soldes;
	}
	
	/** Date de création de la commande, au format jj/mm/aaaa. */
	public static function date(array $order): string {
		$t = self::time($order);
		return $t > 0 ? date('d/m/Y', $t) : '';
	}
	
	private static function getBy(string $champ, string $uid): array {
		global $firebaseDb;
		
		$orders = [];
		
		$query = $firebaseDb->collection('orders')->where($champ, '=', $uid);
		
		foreach ($query->documents() as $snap) {
			if ($snap->exists()) {
				$order = ['id' => $snap->id()] + $snap->data();
				if (self::estPayee($order)) {   // paiement non confirmé : la commande n'apparaît pas
					$orders[] = $order;
				}
			}
		}
		
		// tri fait en PHP : on évite ainsi de créer un index dans Firestore
		usort($orders, static fn (array $a, array $b): int => self::time($b) <=> self::time($a));
		
		return $orders;
	}
		
	private static function time(array $order): int {
		$t = $order['created_at'] ?? null;
		return is_object($t) && method_exists($t, 'get') ? $t->get()->getTimestamp() : 0;
	}
}
