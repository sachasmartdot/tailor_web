<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Size conversion tables and the rules for when they apply.
 *
 * Nothing here is a promise of an exact fit. The numbers come from size charts
 * published by retailers and size-conversion references, and those charts
 * disagree with each other by about one size. So every equivalence this class
 * produces is "indicative", and the interface always says so.
 *
 * Sources used for the tables:
 *  - Women: Crosslist women's chart (US / international / UK / EU), cross-checked
 *    with Hugo Boss womenswear chart and the Hakaru clothing converter.
 *    French women's sizes follow UK + 28 (Zendesk-hosted conversion chart,
 *    sizeguide.net). Retailer "EU" numbers match French ones; German and some
 *    Northern European labels run two numbers lower.
 *  - Men: Hakaru converter and Crosslist men's chart (chest in inches for UK/US,
 *    chest + 10 for EU), cross-checked with the PUMA men's chart (FR = DE = EU).
 *  - Children: awajis.com, size.ly, wearview.co and primecalcpro (EU size = height
 *    in cm, UK/US sizes follow age). France labels children by age in years.
 *    The sources agree from 2 to 8 years, so the guide stops there.
 *
 * What we deliberately do NOT convert:
 *  - Men's trousers (sold by waist and length, not by letter).
 *  - Traditional outfits and "other" garments (no international standard).
 *  - Unisex or unspecified garments get BOTH the men's and the women's reading,
 *    because no standard exists for them.
 */
final class SizeGuide
{
    /** Sizing systems the customer can choose from. */
    public const REFERENCES = [
        'FR' => 'France (FR)',
        'EU' => 'Europe (EU)',
        'US' => 'United States (US)',
        'UK' => 'United Kingdom (UK)',
    ];

    /** Short country names, used on the small buttons. */
    public const REFERENCE_SHORT = [
        'FR' => 'France',
        'EU' => 'Europe',
        'US' => 'USA',
        'UK' => 'UK',
    ];

    /** Garment categories a tailor can pick (optional). */
    public const CATEGORIES = [
        'men'      => 'Men',
        'women'    => 'Women',
        'unisex'   => 'Unisex',
        'children' => 'Children',
    ];

    /** Garment types a tailor can pick (optional). */
    public const GARMENT_TYPES = [
        'dress'       => 'Dress',
        'top'         => 'Shirt / Top',
        'trousers'    => 'Trousers',
        'skirt'       => 'Skirt',
        'jacket'      => 'Jacket / Suit',
        'traditional' => 'Traditional outfit',
        'other'       => 'Other',
    ];

    /** General sizes shown for adults and for models with no category. */
    private const LETTER_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    private const WOMEN = [
        'XS'  => ['US' => '2',     'UK' => '6',     'EU' => '34',    'FR' => '34'],
        'S'   => ['US' => '4–6',   'UK' => '8–10',  'EU' => '36–38', 'FR' => '36–38'],
        'M'   => ['US' => '8–10',  'UK' => '12–14', 'EU' => '40–42', 'FR' => '40–42'],
        'L'   => ['US' => '12–14', 'UK' => '16–18', 'EU' => '44–46', 'FR' => '44–46'],
        'XL'  => ['US' => '16–18', 'UK' => '20–22', 'EU' => '48–50', 'FR' => '48–50'],
        'XXL' => ['US' => '20–22', 'UK' => '24–26', 'EU' => '52–54', 'FR' => '52–54'],
    ];

    private const MEN = [
        'XS'  => ['US' => '34',    'UK' => '34',    'EU' => '44',    'FR' => '44'],
        'S'   => ['US' => '36',    'UK' => '36',    'EU' => '46',    'FR' => '46'],
        'M'   => ['US' => '38–40', 'UK' => '38–40', 'EU' => '48–50', 'FR' => '48–50'],
        'L'   => ['US' => '42–44', 'UK' => '42–44', 'EU' => '52–54', 'FR' => '52–54'],
        'XL'  => ['US' => '46–48', 'UK' => '46–48', 'EU' => '56–58', 'FR' => '56–58'],
        'XXL' => ['US' => '50–52', 'UK' => '50–52', 'EU' => '60–62', 'FR' => '60–62'],
    ];

    private const CHILDREN = [
        '2 years' => ['US' => '2T', 'UK' => '18–24 months', 'EU' => '92 cm',  'FR' => '2 years'],
        '3 years' => ['US' => '3T', 'UK' => '2–3 years',    'EU' => '98 cm',  'FR' => '3 years'],
        '4 years' => ['US' => '4T', 'UK' => '3–4 years',    'EU' => '104 cm', 'FR' => '4 years'],
        '5 years' => ['US' => '5',  'UK' => '4–5 years',    'EU' => '110 cm', 'FR' => '5 years'],
        '6 years' => ['US' => '6',  'UK' => '5–6 years',    'EU' => '116 cm', 'FR' => '6 years'],
        '7 years' => ['US' => '7',  'UK' => '6–7 years',    'EU' => '122 cm', 'FR' => '7 years'],
        '8 years' => ['US' => '8',  'UK' => '7–8 years',    'EU' => '128 cm', 'FR' => '8 years'],
    ];

    private const NOTE_WOMEN = 'Indicative only. Based on commonly published women\'s size charts; brands and tailors can differ by one size. FR and EU numbers are the same in this chart, although some German and Northern European labels run two numbers lower.';
    private const NOTE_MEN = 'Indicative only. This is a chest-based chart for tops, jackets and suits. Dress shirts are often sold by neck size, so check the tailor\'s sizing notes. FR and EU numbers are the same for men.';
    private const NOTE_CHILDREN = 'Indicative only. Children\'s sizes follow age and height, and brands differ. Height in centimetres is the most reliable guide.';
    private const NOTE_BOTH = 'Indicative only. There is no official standard for unisex garments, or for garments where the tailor has not chosen a category. Both the men\'s and the women\'s readings are shown. If in doubt, ask the tailor.';

    /** The general sizes a customer can pick for a category. */
    public static function sizesFor(string $category): array
    {
        return $category === 'children' ? array_keys(self::CHILDREN) : self::LETTER_SIZES;
    }

    /**
     * Describes what the guide can say about a category and garment type.
     *
     * Returns:
     *  - available: whether any conversion can be shown
     *  - reason:    why not, when it is not available
     *  - note:      the caveat to show next to the conversion
     *  - rows:      size => [reference => text], ready to display
     */
    public static function describe(string $category, string $garmentType): array
    {
        $category    = isset(self::CATEGORIES[$category]) ? $category : '';
        $garmentType = isset(self::GARMENT_TYPES[$garmentType]) ? $garmentType : '';

        if ($category === 'children') {
            return self::available(self::NOTE_CHILDREN, self::rowsFrom(self::CHILDREN));
        }

        if ($garmentType === 'traditional' || $garmentType === 'other') {
            return self::unavailable('There is no reliable international size equivalent for traditional or one-off pieces. Please follow the tailor\'s own sizing notes.');
        }

        if ($category === 'women') {
            return self::available(self::NOTE_WOMEN, self::rowsFrom(self::WOMEN));
        }

        if ($category === 'men') {
            if ($garmentType === 'trousers' || $garmentType === 'dress' || $garmentType === 'skirt') {
                return self::unavailable('Men\'s trousers are sized by waist and length, so a letter size cannot be converted reliably. Please follow the tailor\'s own sizing notes.');
            }
            return self::available(self::NOTE_MEN, self::rowsFrom(self::MEN));
        }

        // Unisex, or an older garment with no category: show both readings.
        if ($garmentType === 'trousers' || $garmentType === 'dress' || $garmentType === 'skirt') {
            return self::unavailable('This type of garment has no reliable size equivalent without knowing whether it is cut for men or women. Please follow the tailor\'s own sizing notes.');
        }

        $rows = [];
        foreach (self::LETTER_SIZES as $size) {
            foreach (array_keys(self::REFERENCES) as $ref) {
                $rows[$size][$ref] = 'Men\'s ' . $ref . ' ' . self::MEN[$size][$ref]
                    . ' · Women\'s ' . $ref . ' ' . self::WOMEN[$size][$ref];
            }
        }
        return self::available(self::NOTE_BOTH, $rows);
    }

    /**
     * Builds the size details that get saved with a cart item and its order.
     * Returns null when the size is not valid for the category, or when a
     * conversion is available but the customer has not chosen a sizing system.
     */
    public static function selection(string $category, string $garmentType, string $size, string $reference): ?array
    {
        if (!in_array($size, self::sizesFor($category), true)) {
            return null;
        }

        $guide = self::describe($category, $garmentType);
        $base  = [
            'size_reference'  => '',
            'size_equivalent' => '',
            'size_category'   => isset(self::CATEGORIES[$category]) ? $category : '',
            'garment_type'    => isset(self::GARMENT_TYPES[$garmentType]) ? $garmentType : '',
            'size_indicative' => true,
        ];

        if (!$guide['available']) {
            return $base;
        }

        if (!isset(self::REFERENCES[$reference])) {
            return null;
        }

        $base['size_reference']  = $reference;
        $base['size_equivalent'] = (string) ($guide['rows'][$size][$reference] ?? '');
        return $base;
    }

    /**
     * A short line for showing a saved size, for example "M → FR 40–42 (Women, indicative)".
     * Works on older items that only have a plain size.
     */
    public static function summary(array $item): string
    {
        $size = trim((string) ($item['taille'] ?? ''));
        if ($size === '') {
            return '';
        }

        $equivalent = trim((string) ($item['size_equivalent'] ?? ''));
        if ($equivalent === '') {
            return $size;
        }

        $category = self::CATEGORIES[(string) ($item['size_category'] ?? '')] ?? '';
        $label    = $category !== '' ? $category . ', indicative' : 'indicative';

        return $size . ' → ' . $equivalent . ' (' . $label . ')';
    }

    /** Readable label for a garment category, or '' when unknown. */
    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? '';
    }

    /** Readable label for a garment type, or '' when unknown. */
    public static function typeLabel(string $garmentType): string
    {
        return self::GARMENT_TYPES[$garmentType] ?? '';
    }

    private static function available(string $note, array $rows): array
    {
        return ['available' => true, 'reason' => '', 'note' => $note, 'rows' => $rows];
    }

    private static function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'note' => '', 'rows' => []];
    }

    /** Turns a table of raw values into display text such as "FR 40–42". */
    private static function rowsFrom(array $table): array
    {
        $rows = [];
        foreach ($table as $size => $values) {
            foreach (array_keys(self::REFERENCES) as $ref) {
                $rows[$size][$ref] = $ref . ' ' . $values[$ref];
            }
        }
        return $rows;
    }
}