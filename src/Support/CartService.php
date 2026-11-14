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
     * Adds a garment to the cart with the customer's choices.
     *
     * $sizeInfo comes from SizeGuide::selection(). It carries the sizing system the
     * customer picked and the equivalent we showed them. Everything in the cart item
     * is copied into the order at checkout, so the tailor sees the same details.
     */
    public static function add(
        array $model,
        string $couleur = '',
        string $taille = '',
        array $sizeInfo = []
    ): void {
        $_SESSION[self::SESSION_KEY][] = [
            'model_id'   => $model['id'],
            'title'      => $model['title'] ?? '',
            'prix'       => (float) ($model['prix'] ?? 0),
            'image'      => $model['image'] ?? '',
            'tailor_uid' => $model['uid'] ?? '',

            // What the customer chose
            'couleur' => $couleur,
            'taille'  => $taille,

            // Sizing system details (empty for older items)
            'size_reference'  => (string) ($sizeInfo['size_reference'] ?? ''),
            'size_equivalent' => (string) ($sizeInfo['size_equivalent'] ?? ''),
            'size_category'   => (string) ($sizeInfo['size_category'] ?? ''),
            'garment_type'    => (string) ($sizeInfo['garment_type'] ?? ''),
            'size_indicative' => (bool) ($sizeInfo['size_indicative'] ?? false),

            // The tailor's own sizing note, as it was when the customer ordered
            'size_note' => (string) ($model['size_note'] ?? ''),
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