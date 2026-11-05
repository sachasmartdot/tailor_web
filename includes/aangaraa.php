<?php
declare(strict_types=1);

/*
 * =========================================================
 * CONFIGURATION AANGARAA PAY
 * =========================================================
 *
 * La clé Aangaraa NE DOIT PAS être écrite directement
 * dans ce fichier.
 *
 * Variables d'environnement nécessaires :
 *
 * AANGARAA_KEY
 * AANGARAA_SITE
 *
 * Exemple :
 *
 * export AANGARAA_KEY='TA_NOUVELLE_CLE'
 * export AANGARAA_SITE='https://ton-site-public.ngrok-free.dev'
 *
 * =========================================================
 */

$AANGARAA_KEY_ENV =
    getenv('AANGARAA_KEY');

$AANGARAA_SITE_ENV =
    getenv('AANGARAA_SITE');

if (
    $AANGARAA_KEY_ENV === false
    || trim($AANGARAA_KEY_ENV) === ''
) {

    throw new RuntimeException(
        'AANGARAA_KEY n\'est pas configurée '
        . 'dans l\'environnement serveur.'
    );
}

if (
    $AANGARAA_SITE_ENV === false
    || trim($AANGARAA_SITE_ENV) === ''
) {

    throw new RuntimeException(
        'AANGARAA_SITE n\'est pas configurée '
        . 'dans l\'environnement serveur.'
    );
}

define(
    'AANGARAA_SITE',
    rtrim($AANGARAA_SITE_ENV, '/')
);

const AANGARAA_API =
    'https://api-production.aangaraa-pay.com/api/v1';

const AANGARAA_MTN_OPERATOR =
    'MTN_Cameroon';

const AANGARAA_ORANGE_OPERATOR =
    'Orange_Cameroon';

define(
    'AANGARAA_KEY',
    $AANGARAA_KEY_ENV
);


/*
 * =========================================================
 * APPEL GÉNÉRIQUE À AANGARAA
 * =========================================================
 */
function appelAangaraa(
    string $chemin,
    ?array $body = null
): ?array {

    $ch = curl_init(
        AANGARAA_API . $chemin
    );

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        /*
         * Temps maximum pour établir la connexion.
         */
        CURLOPT_CONNECTTIMEOUT => 10,

        /*
         * Temps maximum total de la requête.
         */
        CURLOPT_TIMEOUT => 60,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);


    if ($body !== null) {

        curl_setopt(
            $ch,
            CURLOPT_POST,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode(
                $body,
                JSON_UNESCAPED_UNICODE
            )
        );
    }


    $reponse = curl_exec($ch);

    $erreur = curl_error($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    /*
     * Une erreur cURL est différente d'un statut
     * FAILED retourné par Aangaraa.
     *
     * On remonte donc l'exception afin que le code
     * appelant puisse conserver le paiement en PENDING.
     */
    if ($reponse === false) {

        throw new RuntimeException(
            'Connexion à Aangaraa Pay impossible : '
            . $erreur
        );
    }


    /*
     * Réponse JSON.
     */
    $res = json_decode(
        $reponse,
        true
    );


    if (!is_array($res)) {

        /*
         * On ne transforme pas automatiquement cela
         * en FAILED.
         *
         * Une réponse invalide peut être liée à un problème
         * temporaire du service.
         */
        throw new RuntimeException(
            'Réponse JSON invalide de Aangaraa Pay.'
        );
    }


    /*
     * Si Aangaraa renvoie explicitement 504, on signale
     * le timeout à l'appelant.
     *
     * L'appelant pourra alors conserver PENDING.
     */
    if (
        $httpCode === 504
        || (int) ($res['statusCode'] ?? 0) === 504
    ) {

        throw new RuntimeException(
            'Aangaraa Pay a retourné une erreur HTTP 504 '
            . '(Gateway Timeout).'
        );
    }


    return $res;
}


/*
 * =========================================================
 * RETRAIT AANGARAA
 * =========================================================
 */
function retraitAangaraa(
    int $montant,
    string $operateur,
    string $telephone,
    string $nom
): array {

    $res = appelAangaraa(
        '/aangaraa-pay/withdrawal',
        [
            'app_key' =>
                AANGARAA_KEY,

            'phone_number' =>
                $telephone,

            'amount' =>
                (string) $montant,

            'payment_method' =>
                $operateur,

            'username' =>
                $nom,
        ]
    );


    if ($res === null) {

        throw new RuntimeException(
            'Réponse invalide de Aangaraa Pay.'
        );
    }


    $code = (int) (
        $res['statusCode'] ?? 500
    );

    $statut = strtoupper(
        (string) (
            $res['data']['status']
            ?? ''
        )
    );


    if (
        $code >= 300
        || in_array(
            $statut,
            [
                'FAILED',
                'REJECTED',
            ],
            true
        )
    ) {

        throw new RuntimeException(
            (string) (
                $res['message']
                ?? 'Réponse invalide de Aangaraa Pay'
            )
        );
    }


    return [

        'etat' =>
            $statut === 'SUCCESSFUL'
                ? 'effectue'
                : 'en_cours',

        'ref' =>
            (string) (
                $res['data']['reference_id']
                ?? ''
            ),
    ];
}


/*
 * =========================================================
 * STATUT D'UN RETRAIT
 * =========================================================
 */
function statutRetraitAangaraa(
    string $ref,
    string $operateur
): string {

    $res = appelAangaraa(
        '/check_withdrawal_status/'
        . rawurlencode($ref)
        . '?payment_method='
        . urlencode($operateur)
    );


    if ($res === null) {
        return 'en_cours';
    }


    $statut = strtoupper(
        (string) (
            $res['status'] ?? ''
        )
    );


    if ($statut === 'SUCCESSFUL') {
        return 'effectue';
    }

    if ($statut === 'FAILED') {
        return 'echec';
    }

    return 'en_cours';
}
