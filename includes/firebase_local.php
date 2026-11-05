<?php
// includes/firebase_local.php
// Réglages Firebase pour le travail EN LOCAL (émulateurs). Renvoie une "Factory".
// Utilisé automatiquement par bootstrap.php quand $useEmulator = true.
// Tu n'as pas besoin de comprendre ce fichier : il ne sert qu'en local.

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;

// Où sont les émulateurs (adresses du terminal 1)
putenv('FIREBASE_AUTH_EMULATOR_HOST=127.0.0.1:9099');
putenv('FIRESTORE_EMULATOR_HOST=127.0.0.1:8080');

// Faux "badge Google" : cette clé est fabriquée, elle ne donne accès à RIEN.
$fauxCompte = [
    'type'           => 'service_account',
    'project_id'     => 'demo-tailor',
    'private_key_id' => 'local-fake-key',
    'private_key'    => <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCuokbgpN9wuUWG
uDU5bmWmAKiHG+DZxi6LGavlLD6hJV7eUZpZVw4a3g80WbivHiOHU4ywZJU8FhUa
68ukV2hfqQ1f+un0SsoPz5AMBs+6fp3zrv0dXt9l9SWl0O+rB1TP2SDe3OFK13nr
H5emo+jB42YMEaIZObHbeyw/YPqoCNqictbHdoh6dFv11vZK0prbdl6fHeGZC80y
7lGZG3j98NOmhJ1JWpIdrwC02Xb3ZjZfgGKWm1rwo0AnEjb55CJ1JJTiYCOVCerz
gFFXBGWICZry/b3SFozm9iqxeYcNHxcZq8vNDbExOcBArwCs88fG+O6bCBfr0NHh
/SWVnj//AgMBAAECggEAAM+3NZb+hMcPmCMl+CjXpxBMuCf8gPaHQ9UwM8XjWptS
WAUYCv85DQ3oEy2xinrA3oGYXz9wZBGUMoZS8WYIkZ029zGywZRRYQZtXGjxsSOi
dz3iZi8JKa+cZ6tTWb+UeMzA9tge4dqjceC5MyiCzdfRIxA36RcYCPWHDJpWP86c
wPCOhjGeyrATtjzFGWoS1eoRumn0xj5AOS0yvVjCFHnvA5Aoo03LfqLR77F0sNw2
kR7reB6MC6j/DVpEtrihbMdO/+0Qg9WKBWn177N8DFH9bUIfTNhFY75YD+yWTuv3
SwqASWqcTczLM4nlz8IFlLwsYD/qheuINQd3BFsC6QKBgQDkeZX9QJuIF4aaR3WP
g5Utpna7LNr/piescHXNAy1huWg+eP3CxrS/poO/Z1FGfSh76tUwnrJdk9jtD4wJ
o2omEW2nYGiOxemcEH6TK08l8xbUZMieJnIFeIJ3mEEP2jTujfcn4CXKnCTRpnfd
kRGAdh8h1Y/cjIf0oCSCGedDOQKBgQDDrCy/rJEHsaWJ7d7Oc9z0JzTnjxx4sN+I
OsKSa3m2YzVcAuzVE7qW1RC2JvMPANZhepCjAz8Gb6vhTQ+UVcREWQJb3Y1HjD5G
hNx05FAm8dDwYgdBU7ow0yRqJovsDUWd0hQ1BPPn6yx4ny0spXts7rFaE9TnBHCx
EVwsQGuE9wKBgHk1R60jKV/5mx8d6fBJDks5lKOYGFan/0QsJVKt7NV7VyJNbdJ3
OEy0OhDA7w1GOMQEwTUZ++ZJGTwqk1BJsXagqFuzecTEmjqBRaUtxL+NRv9GuGeu
Xa8pT43C6EFQseQczJILCAGE9MOEXPLekzpjcACzG68VnsrYaoXa2CWBAoGAPYFJ
L5aWn8x/NDPI7SwOL9CDGKYrVOSD3tZZgpy1zVax1xI0T2W8KN0gO6FSlUy2Fj3o
aNUEYomHhXIri3zKDJHN5AJn/i1fBduNUFDTwOoRuv2VOjuPMvmrYJJtOO2TDGCU
aXX9FNEfUWr3UClT+pGsvZuuOE90/LNwPZiiM1ECgYBG7kG/mEqNhAO4PeOqtwp+
sEW0FJGOYX4MDLk/s28ny2riXZ2BD/nMj78hhyZhZbBw6qPTM10tr10/gnvYx2xX
cdtIefxa0a8mVSAFlYQVi5pf8qzhEurmOApTnt2VUt7WjcVMNvuEtM9W2s3dlpXA
IRi4/20z68nInLgdTzKeYQ==
-----END PRIVATE KEY-----
KEY,
    'client_email'   => 'local-fake@demo-tailor.iam.gserviceaccount.com',
    'client_id'      => '0',
    'token_uri'      => 'https://oauth2.googleapis.com/token',
];

// Quand kreait veut faire valider le badge chez Google, on répond nous-mêmes "OK".
$realHandler = Utils::chooseHandler();
$fakeGoogleToken = static function ($request, array $options) use ($realHandler) {
    if ($request->getUri()->getHost() === 'oauth2.googleapis.com') {
        return Create::promiseFor(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['access_token' => 'owner', 'expires_in' => 3600, 'token_type' => 'Bearer'])
        ));
    }
    return $realHandler($request, $options);
};

return (new Factory)
    ->withServiceAccount($fauxCompte)
    ->withHttpClientOptions(HttpClientOptions::default()->withGuzzleHandler($fakeGoogleToken));
