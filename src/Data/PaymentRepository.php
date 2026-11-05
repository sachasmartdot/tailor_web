<?php
declare(strict_types=1);

namespace App\Data;

use Google\Cloud\Firestore\FieldValue;

/**
 * Registre des tentatives de paiement Aangaraa.
 *
 * Une tentative possède un identifiant unique (transaction_id) et référence
 * les commandes créées avant l'appel à Aangaraa. Cela permet au webhook et au
 * callback de travailler même si la session PHP du client disparaît.
 */
class PaymentRepository
{
    public static function create(array $data): void
    {
        global $firebaseDb;

        $id = (string) ($data['transaction_id'] ?? '');
        if ($id === '') {
            throw new \RuntimeException('Référence de paiement manquante.');
        }

        $firebaseDb->collection('payments')->document($id)->set([
            'transaction_id' => $id,
            'client_uid' => (string) ($data['client_uid'] ?? ''),
            'amount' => (int) ($data['amount'] ?? 0),
            'operator' => (string) ($data['operator'] ?? ''),
            'phone_number' => (string) ($data['phone_number'] ?? ''),
            'order_ids' => array_values($data['order_ids'] ?? []),
            'pay_token' => (string) ($data['pay_token'] ?? ''),
            'status' => strtoupper((string) ($data['status'] ?? 'PENDING')),
            'gateway_txnid' => '',
            'reason' => '',
            'created_at' => FieldValue::serverTimestamp(),
            'updated_at' => FieldValue::serverTimestamp(),
        ]);
    }

    /**
     * Ramène les mots d'Aangaraa (SUCCESS, COMPLETED, CANCELED...) aux 5 états utilisés ici :
     * PENDING, SUCCESSFUL, FAILED, CANCELLED, EXPIRED. Un mot inconnu reste PENDING (jamais "payé").
     */
    public static function normaliser(string $statut): string
    {
        $s = strtoupper(trim($statut));

        if (in_array($s, ['SUCCESSFUL', 'SUCCESS', 'SUCCEEDED', 'COMPLETED', 'COMPLETE', 'PAID'], true)) {
            return 'SUCCESSFUL';
        }
        if (in_array($s, ['FAILED', 'FAILURE', 'REJECTED', 'DECLINED', 'ERROR'], true)) {
            return 'FAILED';
        }
        if (in_array($s, ['CANCELLED', 'CANCELED'], true)) {
            return 'CANCELLED';
        }
        if (in_array($s, ['EXPIRED', 'TIMEOUT'], true)) {
            return 'EXPIRED';
        }

        return 'PENDING';
    }

    /** Lit le statut dans une réponse d'Aangaraa (status, data.status ou transaction.status). '' si absent. */
    public static function lireStatut(?array $res): string
    {
        foreach ([['status'], ['data', 'status'], ['transaction', 'status']] as $chemin) {
            $v = $res;
            foreach ($chemin as $cle) {
                $v = (is_array($v) && array_key_exists($cle, $v)) ? $v[$cle] : null;
            }
            if (is_string($v) && trim($v) !== '') {
                return self::normaliser($v);
            }
        }

        return '';
    }

    public static function find(string $transactionId): ?array
    {
        global $firebaseDb;

        $snap = $firebaseDb->collection('payments')->document($transactionId)->snapshot();
        return $snap->exists() ? ['id' => $snap->id()] + $snap->data() : null;
    }

    public static function findByPayToken(string $payToken): ?array
    {
        global $firebaseDb;

        if ($payToken === '') {
            return null;
        }

        foreach ($firebaseDb->collection('payments')->where('pay_token', '=', $payToken)->documents() as $snap) {
            if ($snap->exists()) {
                return ['id' => $snap->id()] + $snap->data();
            }
        }

        return null;
    }

    /**
     * Rend l'état du paiement idempotent : callback et webhook peuvent appeler
     * cette méthode plusieurs fois sans recréer une commande ni créditer deux fois.
     */
    public static function applyGatewayStatus(
        string $transactionId,
        string $status,
        string $gatewayTxnId = '',
        string $reason = ''
    ): ?array {
        global $firebaseDb;

        $status = self::normaliser($status);

        $paymentRef = $firebaseDb->collection('payments')->document($transactionId);

        return $firebaseDb->runTransaction(function ($transaction) use (
            $firebaseDb,
            $paymentRef,
            $status,
            $gatewayTxnId,
            $reason
        ) {
            $paymentSnap = $transaction->snapshot($paymentRef);
            if (!$paymentSnap->exists()) {
                return null;
            }

            $payment = ['id' => $paymentSnap->id()] + $paymentSnap->data();
            $current = strtoupper((string) ($payment['status'] ?? 'PENDING'));

            // Un paiement final ne revient jamais à PENDING/FAILED.
            if (in_array($current, ['SUCCESSFUL'], true)) {
                return $payment;
            }

            // FAILED/CANCELLED/EXPIRED restent finaux sauf si l'on reçoit
            // ensuite SUCCESSFUL : on privilégie alors le statut final confirmé.
            $finalStatuses = ['FAILED', 'CANCELLED', 'EXPIRED'];
            if (in_array($current, $finalStatuses, true) && $status !== 'SUCCESSFUL') {
                return $payment;
            }

            // Toutes les lectures de la transaction sont faites avant les écritures.
            $orderRefs = [];
            $orderSnaps = [];
            $orderIds = array_values($payment['order_ids'] ?? []);
            foreach ($orderIds as $orderId) {
                $orderRef = $firebaseDb->collection('orders')->document((string) $orderId);
                $orderRefs[] = $orderRef;
                $orderSnaps[] = $transaction->snapshot($orderRef);
            }

            $transaction->update($paymentRef, [
                ['path' => 'status', 'value' => $status],
                ['path' => 'gateway_txnid', 'value' => $gatewayTxnId],
                ['path' => 'reason', 'value' => $reason],
                ['path' => 'updated_at', 'value' => FieldValue::serverTimestamp()],
            ]);

            foreach ($orderSnaps as $index => $orderSnap) {
                if (!$orderSnap->exists()) {
                    continue;
                }

                $orderRef = $orderRefs[$index];
                $orderUpdate = [
                    ['path' => 'paiement_statut', 'value' => $status],
                    ['path' => 'paiement_txnid', 'value' => $gatewayTxnId],
                    ['path' => 'paiement_reason', 'value' => $reason],
                    ['path' => 'paiement_updated_at', 'value' => FieldValue::serverTimestamp()],
                ];

                // Une commande ne devient exploitable par le tailleur qu'après
                // confirmation du paiement.
                if ($status === 'SUCCESSFUL') {
                    $orderUpdate[] = ['path' => 'statut', 'value' => 'en_attente'];
                }

                $transaction->update($orderRef, $orderUpdate);
            }

            $payment['status'] = $status;
            $payment['gateway_txnid'] = $gatewayTxnId;
            $payment['reason'] = $reason;
            return $payment;
        });
    }
}
