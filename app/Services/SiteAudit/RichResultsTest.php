<?php

/*
 * Created: Mon, 26 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\SiteAudit;

use Dom\HTMLDocument;

/**
 * What Google's Rich Results Test reports for a page: each JSON-LD block is parsed, each item of a
 * type Google shows as a rich result is detected, and an item missing a required field is invalid
 * while one missing a recommended field only gets a warning. The page passes with at least one
 * item, no invalid item and no unparsable block.
 * ponytail: JSON-LD only, for the types Iris writes plus reviews and image metadata; add microdata or
 * more types when a website uses them.
 */
class RichResultsTest
{
    private const array RICH_RESULT_TYPES = ['Product', 'ProductGroup', 'BreadcrumbList', 'FAQPage', 'Review', 'ImageObject'];

    private const array RULES = [
        'Product'         => ['required' => ['name'], 'one_of' => ['offers', 'review', 'aggregateRating'], 'recommended' => ['image', 'description', 'brand', 'sku']],
        'ProductGroup'    => ['required' => ['name'], 'recommended' => ['productGroupID', 'variesBy', 'hasVariant']],
        'Offer'           => ['one_of' => ['price', 'priceSpecification'], 'recommended' => ['priceCurrency', 'availability']],
        'AggregateOffer'  => ['required' => ['lowPrice', 'priceCurrency']],
        'AggregateRating' => ['required' => ['ratingValue'], 'one_of' => ['ratingCount', 'reviewCount']],
        'Review'          => ['required' => ['author', 'reviewRating']],
        'Rating'          => ['required' => ['ratingValue']],
        'BreadcrumbList'  => ['required' => ['itemListElement']],
        'FAQPage'         => ['required' => ['mainEntity']],
        'Question'        => ['required' => ['name', 'acceptedAnswer']],
        'Answer'          => ['required' => ['text']],
        'ImageObject'     => ['required' => ['contentUrl'], 'one_of' => ['creator', 'creditText', 'copyrightNotice', 'license']],
    ];

    /**
     * @return array{is_valid: bool, unparsable: int, items: array<int, array{type: string, errors: array<int, string>, warnings: array<int, string>}>}
     */
    public static function run(string $html): array
    {
        $document   = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $unparsable = 0;
        $items      = [];

        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = json_decode($script->textContent, true);

            if (!is_array($data)) {
                $unparsable++;
                continue;
            }

            self::detect($data, $items);
        }

        return [
            'is_valid'   => $unparsable === 0 && $items !== [] && collect($items)->every(fn (array $item) => $item['errors'] === []),
            'unparsable' => $unparsable,
            'items'      => $items,
        ];
    }

    /**
     * @param  array{is_valid: bool, unparsable: int, items: array<int, array{type: string, errors: array<int, string>, warnings: array<int, string>}>}  $result
     *
     * @return array<int, string>
     */
    public static function errors(array $result): array
    {
        $errors = collect($result['items'])->flatMap(fn (array $item) => $item['errors']);

        if ($result['unparsable'] > 0) {
            $errors->prepend("Unparsable structured data in {$result['unparsable']} block(s)");
        }

        if ($result['items'] === [] && $result['unparsable'] === 0) {
            $errors->prepend('No rich result items detected');
        }

        return $errors->unique()->values()->all();
    }

    private static function detect(mixed $value, array &$items): void
    {
        if (!is_array($value)) {
            return;
        }

        $type = collect(self::types($value))->first(fn (string $type) => in_array($type, self::RICH_RESULT_TYPES, true));

        if ($type === null) {
            foreach ($value as $child) {
                self::detect($child, $items);
            }

            return;
        }

        $errors   = [];
        $warnings = [];
        self::validate($value, $type, $errors, $warnings);

        $items[] = ['type' => $type, 'errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    private static function validate(array $node, string $path, array &$errors, array &$warnings): void
    {
        $types = self::types($node);

        foreach ($types as $type) {
            $rules = self::RULES[$type] ?? [];

            foreach ($rules['required'] ?? [] as $field) {
                if (!self::has($node, $field)) {
                    $errors[] = "Missing field \"$field\" in $path";
                }
            }

            if (isset($rules['one_of']) && !collect($rules['one_of'])->contains(fn (string $field) => self::has($node, $field))) {
                $errors[] = 'Either "'.implode('", "', $rules['one_of'])."\" should be specified in $path";
            }

            foreach ($rules['recommended'] ?? [] as $field) {
                if (!self::has($node, $field)) {
                    $warnings[] = "Missing field \"$field\" in $path (optional)";
                }
            }
        }

        if (in_array('Offer', $types, true) && self::has($node, 'price') && !is_numeric($node['price'])) {
            $errors[] = "Invalid price format in $path";
        }

        if (in_array('BreadcrumbList', $types, true)) {
            $elements = self::nodes($node['itemListElement'] ?? null);

            foreach ($elements as $index => $element) {
                if (!self::has($element, 'position')) {
                    $errors[] = "Missing field \"position\" in $path.itemListElement";
                }

                if (!self::has($element, 'name') && !self::has(is_array($element['item'] ?? null) ? $element['item'] : [], 'name')) {
                    $errors[] = "Missing field \"name\" in $path.itemListElement";
                }

                if ($index < count($elements) - 1 && !self::has($element, 'item')) {
                    $errors[] = "Missing field \"item\" in $path.itemListElement";
                }
            }
        }

        foreach ($node as $property => $child) {
            if (is_string($property) && !str_starts_with($property, '@')) {
                foreach (self::nodes($child) as $childNode) {
                    self::validate($childNode, "$path.$property", $errors, $warnings);
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function types(array $node): array
    {
        return array_values(array_filter((array)($node['@type'] ?? []), 'is_string'));
    }

    /**
     * @return array<int, array>
     */
    private static function nodes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_is_list($value) ? array_values(array_filter($value, fn ($item) => is_array($item) && !array_is_list($item))) : [$value];
    }

    private static function has(array $node, string $field): bool
    {
        return isset($node[$field]) && $node[$field] !== '' && $node[$field] !== [];
    }
}
