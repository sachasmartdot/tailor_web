<?php
declare(strict_types=1);

namespace App\Support;

class CartService
{
    private const SESSION_KEY = 'cart';

    public static function getItems(): array
    {
        $items = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * Ajoute un modèle au panier avec les choix
     * de personnalisation du client.
     */
    public static function add(
        array $model,
        string $couleur = '',
        string $taille = ''
    ): void {
        $_SESSION[self::SESSION_KEY][] = [
            'model_id' => $model['id'],
            'title' => $model['title'] ?? '',
            'prix' => (float) ($model['prix'] ?? 0),
            'image' => $model['image'] ?? '',
            'tailor_uid' => $model['uid'] ?? '',

            // Personnalisation choisie par le client
            'couleur' => $couleur,
            'taille' => $taille,
        ];
    }

    public static function count(): int
    {
        return count(self::getItems());
    }

    public static function total(): float
    {
        return (float) array_sum(
            array_column(self::getItems(), 'prix')
        );
    }

    public static function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }
}
