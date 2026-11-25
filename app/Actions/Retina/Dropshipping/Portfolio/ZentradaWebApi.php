<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Enums\Helpers\TaxCategories\TaxCategoryTypeEnum;
use App\Helpers\NaturalLanguage;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\TaxCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZentradaWebApi extends RetinaAction
{
    private const int MAX_IMAGES = 10;

    private const int NAME_MAX_LENGTH = 50;

    private const int BRAND_MAX_LENGTH = 40;

    private const int SHORT_DESCRIPTION_MAX_LENGTH = 1000;

    private const int LONG_TEXT_MAX_LENGTH = 5000;

    private const int GPSR_FIELD_MAX_LENGTH = 150;

    public const int OFFER_VALID_DAYS = 7;

    public const int MAX_VOLUME_TIERS = 3;

    public const string EXCLUDED_CODE_SUFFIX = 'EXP';

    private const array COLUMNS = [
        'market_place_allocation',
        'item_number',
        'ean_code',
        'ean_ve',
        'product_description',
        'short_product_description',
        'detailed_product_description',
        'brand_name',
        'image',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'image7',
        'image8',
        'image9',
        'image10',
        'currency',
        'VAT',
        'quantity_of_units_per_package',
        'minimum_order_quantity_in_packing_units',
        'net_price_per_unit',
        'promotion_discount',
        'volumedbasedpricing_quantity1',
        'volumebasedpricing_price1',
        'volumedbasedpricing_quantity2',
        'volumebasedpricing_price2',
        'volumedbasedpricing_quantity3',
        'volumebasedpricing_price3',
        'available_quantity_in_packing_units',
        'recommended_retail_price',
        'shop_product_categoryID1',
        'shop_product_categoryID2',
        'shop_product_categoryID3',
        'activ_until',
        'weight',
        'collection',
        'statistical_number',
        'country_of_origin',
        'dangerous_goods',
        'energyEfficiency',
        'energyEffImage',
        'weightNetPcP',
        'weightGrossPcP',
        'volumeGrossPcP',
        'lengthPU',
        'widthPU',
        'heightPU',
        'lengthPc',
        'widthPc',
        'heightPc',
        'puPallet',
        'puLayerPallet',
        'hazardCode',
        'hazardName',
        'flashPoint',
        'electroClassification',
        'isBatterieIncluded',
        'isLithium',
        'VocPerc',
        'isFood',
        'color',
        'material',
        'charge',
        'mhd',
        'GPSRIsRequired',
        'certificate_CE',
        'certificate_additional',
        'availableLanguages',
        'producerName',
        'producerAddress',
        'productManagerEMail',
        'operatingInstructions',
        'instructionsDocument1',
        'instructionsDocument2',
        'instructionsDocument3',
        'warningNote',
        'warningNote1',
        'warningNote2',
        'warningNote3',
    ];

    private const array DOCUMENT_SCOPES = [
        'certificate_CE'         => [TradeAttachmentScopeEnum::DOC],
        'certificate_additional' => [TradeAttachmentScopeEnum::CPSR, TradeAttachmentScopeEnum::TEST_REPORTS],
        'instructionsDocument'   => [TradeAttachmentScopeEnum::HOW_TO_USE],
        'warningNote'            => [TradeAttachmentScopeEnum::SDS, TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS],
    ];

    public function handle(Shop $shop): StreamedResponse
    {
        $context = [
            'currency'         => $shop->currency?->code,
            'vat'              => $this->getVatRate($shop),
            'domain'           => $shop->website?->domain,
            'activeUntil'      => now()->addDays(self::OFFER_VALID_DAYS)->format('d.m.Y'),
            'promotion'        => $this->getPromotionDiscount($shop),
            'volumeTiers'      => $this->getVolumeTiers($shop),
            'responsibleParty' => $shop->organisation->getGpsrResponsiblePerson('eu') ?? [],
        ];

        $query = Product::query()
            ->where('products.shop_id', $shop->id)
            ->where('products.is_for_sale', true)
            ->where('products.price', '>', 0)
            ->where('products.available_quantity', '>', 0)
            ->whereNull('products.exclusive_for_customer_id')
            ->whereRaw("jsonb_array_length(coalesce(products.web_images->'all', '[]'::jsonb)) > 0")
            ->whereRaw('products.code COLLATE "C" NOT ILIKE ?', ['%'.self::EXCLUDED_CODE_SUFFIX])
            ->with([
                'family:id,code',
                'brands:brands.id,brands.name',
                'tradeUnits:trade_units.id,trade_units.code,trade_units.barcode,trade_units.tariff_code,trade_units.gpsr_eu_responsible,trade_units.gpsr_class_languages',
                'tradeUnits.brands:brands.id,brands.name',
            ])
            ->select([
                'products.id',
                'products.family_id',
                'products.code',
                'products.name',
                'products.barcode',
                'products.description',
                'products.description_extra',
                'products.price',
                'products.rrp',
                'products.units',
                'products.available_quantity',
                'products.marketing_weight',
                'products.gross_weight',
                'products.marketing_dimensions',
                'products.marketing_ingredients',
                'products.tariff_code',
                'products.country_of_origin',
                'products.web_images',
                'products.un_number',
                'products.proper_shipping_name',
                'products.gpsr_warnings',
                'products.gpsr_manual',
                'products.gpsr_class_languages',
            ])
            ->orderBy('products.id');

        return response()->streamDownload(function () use ($query, $context) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS, ';', '"', '');

            $query->chunk(200, function (Collection $rows) use ($handle, $context) {
                $documents = $this->getDocumentLinks($rows->pluck('id')->all(), $context['domain']);

                foreach ($rows as $row) {
                    fputcsv($handle, $this->mapRow($row, $context, $documents[$row->id] ?? []), ';', '"', '');
                }
            });

            fclose($handle);
        }, 'items.csv', [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    /**
     * Zentrada keeps the last value it imported for a field left empty, so the promotion is always
     * published, as an explicit 0 when there is none: an empty column leaves a discount running forever.
     *
     * Zentrada prices a piece and multiplies it by the pieces in the pack, so the piece price is rounded
     * up to the cent: Zentrada's pack total is then never below the pack price aiku sells and invoices at.
     *
     * Products missing from a later feed are not reliably taken off Zentrada, so every offer expires
     * OFFER_VALID_DAYS after the feed last listed it.
     *
     * @param array{currency: ?string, vat: string, domain: ?string, activeUntil: string, promotion: float, volumeTiers: array<int, array{quantity: int, discount: float}>, responsibleParty: array<string, mixed>} $context
     * @param array<string, array<int, string>> $documents
     */
    private function mapRow(Product $row, array $context, array $documents): array
    {
        $piecesPerPackage = $this->piecesPerPackage($row);
        $packPriceInCents = (int) round((float) $row->price * 100);
        $images           = $this->extractImages($row->web_images);
        $mainTradeUnit    = $this->getMainTradeUnit($row);
        $pieceEan         = $this->pieceEan($row, $mainTradeUnit, $piecesPerPackage);
        $producer         = $this->getProducer($row, $context['responsibleParty']);
        $dimensions       = $this->pieceDimensionsInCentimetres($row->marketing_dimensions);
        $volumeTiers      = $this->volumeTierColumns($context['volumeTiers'], $packPriceInCents, $piecesPerPackage);

        return [
            'market_place_allocation'                 => 'EU',
            'item_number'                             => $row->code,
            'ean_code'                                => $pieceEan,
            'ean_ve'                                  => $row->barcode && $row->barcode !== $pieceEan ? $row->barcode : '',
            'product_description'                     => $this->limitAtWord($this->plainText($row->name), self::NAME_MAX_LENGTH),
            'short_product_description'               => $this->shortDescription($row, $piecesPerPackage),
            'detailed_product_description'            => $this->detailedDescription($row),
            'brand_name'                              => mb_substr($this->getBrandName($row), 0, self::BRAND_MAX_LENGTH),
            'image'                                   => $images[0] ?? '',
            'image2'                                  => $images[1] ?? '',
            'image3'                                  => $images[2] ?? '',
            'image4'                                  => $images[3] ?? '',
            'image5'                                  => $images[4] ?? '',
            'image6'                                  => $images[5] ?? '',
            'image7'                                  => $images[6] ?? '',
            'image8'                                  => $images[7] ?? '',
            'image9'                                  => $images[8] ?? '',
            'image10'                                 => $images[9] ?? '',
            'currency'                                => $context['currency'],
            'VAT'                                     => $context['vat'],
            'quantity_of_units_per_package'           => $piecesPerPackage,
            'minimum_order_quantity_in_packing_units' => 1,
            'net_price_per_unit'                      => $this->formatCents($this->piecePriceInCents($packPriceInCents, $piecesPerPackage)),
            'promotion_discount'                      => $this->formatNumber($context['promotion']),
            'volumedbasedpricing_quantity1'           => $volumeTiers[0]['quantity'] ?? '',
            'volumebasedpricing_price1'               => $volumeTiers[0]['price'] ?? '',
            'volumedbasedpricing_quantity2'           => $volumeTiers[1]['quantity'] ?? '',
            'volumebasedpricing_price2'               => $volumeTiers[1]['price'] ?? '',
            'volumedbasedpricing_quantity3'           => $volumeTiers[2]['quantity'] ?? '',
            'volumebasedpricing_price3'               => $volumeTiers[2]['price'] ?? '',
            'available_quantity_in_packing_units'     => (int) $row->available_quantity,
            'recommended_retail_price'                => $row->rrp > 0 ? number_format((float) $row->rrp / $piecesPerPackage, 2, '.', '') : '',
            'shop_product_categoryID1'                => '',
            'shop_product_categoryID2'                => '',
            'shop_product_categoryID3'                => '',
            'activ_until'                             => $context['activeUntil'],
            'weight'                                  => $this->kilograms($this->pieceWeight($row, $piecesPerPackage)),
            'collection'                              => $row->family?->code ?? '',
            'statistical_number'                      => $this->getStatisticalNumber($row, $mainTradeUnit),
            'country_of_origin'                       => $row->country_of_origin,
            'dangerous_goods'                         => filled($row->un_number) ? 1 : 0,
            'energyEfficiency'                        => '',
            'energyEffImage'                          => '',
            'weightNetPcP'                            => $this->kilograms($row->marketing_weight),
            'weightGrossPcP'                          => $this->kilograms($row->gross_weight ? $row->gross_weight / $piecesPerPackage : null),
            'volumeGrossPcP'                          => '',
            'lengthPU'                                => '',
            'widthPU'                                 => '',
            'heightPU'                                => '',
            'lengthPc'                                => $dimensions['l'] ?? '',
            'widthPc'                                 => $dimensions['w'] ?? '',
            'heightPc'                                => $dimensions['h'] ?? '',
            'puPallet'                                => '',
            'puLayerPallet'                           => '',
            'hazardCode'                              => filled($row->un_number) ? 'UN '.trim(preg_replace('/^UN\s*/i', '', $row->un_number)) : '',
            'hazardName'                              => (string) $row->proper_shipping_name,
            'flashPoint'                              => '',
            'electroClassification'                   => '',
            'isBatterieIncluded'                      => '',
            'isLithium'                               => '',
            'VocPerc'                                 => '',
            'isFood'                                  => '',
            'color'                                   => '',
            'material'                                => $this->plainText($row->marketing_ingredients),
            'charge'                                  => '',
            'mhd'                                     => '',
            'GPSRIsRequired'                          => filled($row->gpsr_warnings) || filled($row->gpsr_manual) ? 1 : '',
            'certificate_CE'                          => $documents['certificate_CE'][0] ?? '',
            'certificate_additional'                  => $documents['certificate_additional'][0] ?? '',
            'availableLanguages'                      => mb_substr((string) ($row->gpsr_class_languages ?: $mainTradeUnit?->gpsr_class_languages), 0, 200),
            'producerName'                            => $producer['name'],
            'producerAddress'                         => $producer['address'],
            'productManagerEMail'                     => $producer['email'],
            'operatingInstructions'                   => $this->restrictedHtml($row->gpsr_manual, self::LONG_TEXT_MAX_LENGTH),
            'instructionsDocument1'                   => $documents['instructionsDocument'][0] ?? '',
            'instructionsDocument2'                   => $documents['instructionsDocument'][1] ?? '',
            'instructionsDocument3'                   => $documents['instructionsDocument'][2] ?? '',
            'warningNote'                             => $this->restrictedHtml($row->gpsr_warnings, self::LONG_TEXT_MAX_LENGTH),
            'warningNote1'                            => $documents['warningNote'][0] ?? '',
            'warningNote2'                            => $documents['warningNote'][1] ?? '',
            'warningNote3'                            => $documents['warningNote'][2] ?? '',
        ];
    }

    /**
     * Zentrada only takes whole pieces per packing unit, so a pack of a fractional number of units is
     * sold as one packing unit of one piece at the pack price.
     */
    private function piecesPerPackage(Product $product): int
    {
        $units = (float) $product->units;

        return $units >= 1 && $units == floor($units) ? (int) $units : 1;
    }

    public function piecePriceInCents(int $packPriceInCents, int $piecesPerPackage): int
    {
        return intdiv($packPriceInCents + $piecesPerPackage - 1, $piecesPerPackage);
    }

    /**
     * @param array<int, array{quantity: int, discount: float}> $tiers
     *
     * @return array<int, array{quantity: int, price: string}>
     */
    private function volumeTierColumns(array $tiers, int $packPriceInCents, int $piecesPerPackage): array
    {
        return array_map(function (array $tier) use ($packPriceInCents, $piecesPerPackage) {
            $denominator = 10000 * $piecesPerPackage;
            $basisPoints = (int) round($tier['discount'] * 100);

            return [
                'quantity' => $tier['quantity'],
                'price'    => $this->formatCents(intdiv($packPriceInCents * (10000 - $basisPoints) + $denominator - 1, $denominator)),
            ];
        }, $tiers);
    }

    private function getPromotionDiscount(Shop $shop): float
    {
        return max(0, min(99.99, (float) Arr::get($shop->settings, 'zentrada.promotion_discount', 0)));
    }

    /**
     * @return array<int, array{quantity: int, discount: float}>
     */
    public function getVolumeTiers(Shop $shop): array
    {
        $tiers = [];
        foreach (range(1, self::MAX_VOLUME_TIERS) as $tierNumber) {
            $quantity = (int) Arr::get($shop->settings, "zentrada.tier_{$tierNumber}_quantity");
            $discount = (float) Arr::get($shop->settings, "zentrada.tier_{$tierNumber}_discount");

            if ($quantity > 1 && $discount > 0 && $discount < 100) {
                $tiers[$quantity] = ['quantity' => $quantity, 'discount' => $discount];
            }
        }
        ksort($tiers);

        return array_values($tiers);
    }

    /**
     * The pack's own barcode only identifies a single piece when the pack is one piece; a pack of
     * several pieces is identified piece by piece through the trade unit it is made of.
     */
    private function pieceEan(Product $product, ?TradeUnit $mainTradeUnit, int $piecesPerPackage): string
    {
        if ($piecesPerPackage === 1) {
            return (string) ($product->barcode ?: $mainTradeUnit?->barcode);
        }

        return (string) ($mainTradeUnit?->barcode ?: $product->barcode);
    }

    /**
     * Zentrada matches an article by its EAN, so a component's code must never be published as the
     * whole article's. Composed products keep the code of the trade unit they are built around, e.g.
     * the product RTL-02 (lamp plus a UK plug) is built around the trade unit RTL-02, so that trade
     * unit alone carries the article's EAN and tariff code.
     */
    private function getMainTradeUnit(Product $product): ?TradeUnit
    {
        if ($product->tradeUnits->count() === 1) {
            return $product->tradeUnits->first();
        }

        $tradeUnitsSharingCode = $product->tradeUnits->filter(
            fn (TradeUnit $tradeUnit) => $tradeUnit->code !== ''
                && str_starts_with(strtoupper($product->code), strtoupper($tradeUnit->code))
        );

        return $tradeUnitsSharingCode->count() === 1 ? $tradeUnitsSharingCode->first() : null;
    }

    /**
     * A composed product carries the tariff code of every part it is made of, which cannot be
     * declared as one statistical number. The code of the trade unit the product is built around
     * replaces that list, and nothing is sent when no single code can be established: a wrong
     * statistical number is a customs declaration error, an empty one is not.
     */
    private function getStatisticalNumber(Product $product, ?TradeUnit $mainTradeUnit): string
    {
        $productTariffCodes = $this->splitTariffCodes($product->tariff_code);

        if (count($productTariffCodes) === 1) {
            return $productTariffCodes[0];
        }

        return $this->splitTariffCodes($mainTradeUnit?->tariff_code)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function splitTariffCodes(?string $tariffCodes): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $tariffCodes))));
    }

    private function getBrandName(Product $product): string
    {
        $brand = $product->brands->first();

        if (!$brand) {
            $brand = $product->tradeUnits->pluck('brands')->flatten()->first();
        }

        return $brand?->name ?? '';
    }

    /**
     * The EU responsible person is the producer Zentrada shows, unless the product's single trade unit
     * names its own, which is free text: its first line is the name and the rest the address.
     *
     * @param array<string, mixed> $responsibleParty
     *
     * @return array{name: string, address: string, email: string}
     */
    private function getProducer(Product $product, array $responsibleParty): array
    {
        $override = $product->tradeUnits->count() === 1 ? trim((string) $product->tradeUnits->first()->gpsr_eu_responsible) : '';

        if ($override !== '') {
            $overrideEmail = preg_match('/[\w.+-]+@[\w-]+(\.[\w-]+)+/', $override, $emailMatch) ? $emailMatch[0] : '';
            $lines         = array_values(array_filter(array_map('trim', preg_split('/\R/', $overrideEmail ? str_replace($overrideEmail, '', $override) : $override))));

            $name    = array_shift($lines) ?? '';
            $address = implode(', ', $lines);
            $email   = $overrideEmail ?: (string) Arr::get($responsibleParty, 'email', '');
        } else {
            $name    = (string) Arr::get($responsibleParty, 'name', '');
            $address = (string) Arr::get($responsibleParty, 'address', '');
            $email   = (string) Arr::get($responsibleParty, 'email', '');
        }

        return [
            'name'    => mb_substr($name, 0, self::GPSR_FIELD_MAX_LENGTH),
            'address' => mb_substr(preg_replace('/\s*\R\s*/', ', ', $address), 0, self::GPSR_FIELD_MAX_LENGTH),
            'email'   => mb_substr($email, 0, self::GPSR_FIELD_MAX_LENGTH),
        ];
    }

    /**
     * @param array<int, int> $productIds
     *
     * @return array<int, array<string, array<int, string>>>
     */
    private function getDocumentLinks(array $productIds, ?string $domain): array
    {
        if (!$domain || !$productIds) {
            return [];
        }

        $columnsByScope = [];
        foreach (self::DOCUMENT_SCOPES as $column => $scopes) {
            foreach ($scopes as $scope) {
                $columnsByScope[$scope->value] = $column;
            }
        }

        $documents = [];
        DB::table('model_has_attachments')
            ->join('media', 'media.id', '=', 'model_has_attachments.media_id')
            ->where('model_has_attachments.model_type', 'Product')
            ->whereIn('model_has_attachments.model_id', $productIds)
            ->whereIn('model_has_attachments.scope', array_keys($columnsByScope))
            ->orderBy('model_has_attachments.id')
            ->get(['model_has_attachments.model_id', 'model_has_attachments.scope', 'media.ulid'])
            ->sortBy(fn ($attachment) => array_search($attachment->scope, array_keys($columnsByScope)))
            ->each(function ($attachment) use (&$documents, $columnsByScope, $domain) {
                $documents[$attachment->model_id][$columnsByScope[$attachment->scope]][] = 'https://'.$domain.'/attachment/'.$attachment->ulid.'/download';
            });

        return $documents;
    }

    private function pieceWeight(Product $product, int $piecesPerPackage): ?float
    {
        if ($product->marketing_weight) {
            return (float) $product->marketing_weight;
        }

        return $product->gross_weight ? $product->gross_weight / $piecesPerPackage : null;
    }

    private function kilograms(int|float|null $grams): string
    {
        return $grams ? $this->formatNumber(round($grams / 1000, 3)) : '';
    }

    /**
     * @return array<string, string>
     */
    private function pieceDimensionsInCentimetres(mixed $dimensions): array
    {
        if (is_string($dimensions)) {
            $dimensions = json_decode($dimensions, true);
        }

        if (!is_array($dimensions) || !Arr::get($dimensions, 'units') || !Arr::get($dimensions, 'type')) {
            return [];
        }

        $centimetres = [];
        foreach (['l', 'w', 'h'] as $size) {
            if (is_numeric(Arr::get($dimensions, $size)) && $dimensions[$size] > 0) {
                $centimetres[$size] = $this->formatNumber(round($dimensions[$size] * 100, 1));
            }
        }

        return $centimetres;
    }

    private function shortDescription(Product $product, int $piecesPerPackage): string
    {
        $name = $this->plainText($product->name);

        $packing = $piecesPerPackage > 1
            ? '<strong>1 packing unit = '.$piecesPerPackage.' x '.$name.'. Price per piece.</strong>'
            : '<strong>1 unit = 1 x '.$name.'</strong>';

        return $this->restrictedHtml($packing.'<br>'.$product->description, self::SHORT_DESCRIPTION_MAX_LENGTH);
    }

    private function detailedDescription(Product $product): string
    {
        $specifications = array_filter([
            'Weight'          => NaturalLanguage::make()->weight($product->marketing_weight),
            'Shipping weight' => NaturalLanguage::make()->weight($product->gross_weight),
            'Dimensions'      => NaturalLanguage::make()->dimensions($product->marketing_dimensions),
            'Material'        => $this->plainText($product->marketing_ingredients),
        ]);

        $lines = [$product->description, $product->description_extra];
        foreach ($specifications as $label => $value) {
            $lines[] = $label.': '.$value;
        }

        return $this->restrictedHtml(implode('<br>', array_filter($lines)), self::LONG_TEXT_MAX_LENGTH);
    }

    /**
     * Zentrada's text fields take only <br> and <strong>, and refuse phone numbers, email addresses and
     * links to our own websites.
     */
    public function restrictedHtml(?string $html, int $maxLength): string
    {
        $text = (string) $html;
        $text = preg_replace('/<\s*(\/?)\s*b(\s[^>]*)?>/i', '<$1strong>', $text);
        $text = preg_replace('/<\/(p|div|li|h[1-6]|tr)>|<br\s*\/?>/i', "\n", $text);
        $text = strip_tags($text, '<strong>');
        $text = preg_replace('/<strong\b[^>]*>/i', '<strong>', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->removeContactDetails($text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", trim($text));
        $text = preg_replace('/<strong>\s*<\/strong>/', '', $text);
        $text = str_replace("\n", '<br>', trim($text));

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength - strlen('</strong>'));
            $text = preg_replace('/<[^>]*$/', '', $text);
            if (substr_count($text, '<strong>') > substr_count($text, '</strong>')) {
                $text .= '</strong>';
            }
        }

        return $text;
    }

    private function removeContactDetails(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/', '', $text);
        $text = preg_replace('/\b(https?:\/\/|www\.)\S+/i', '', $text);

        return preg_replace_callback(
            '/\+?\d[\d \/().-]{7,}\d/',
            fn (array $match) => preg_match_all('/\d/', $match[0]) >= 9 ? '' : $match[0],
            $text
        );
    }

    private function plainText(?string $text): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function limitAtWord(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $cut       = mb_substr($text, 0, $maxLength + 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $lastSpace ?: $maxLength), " -,;:");
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function formatNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    private function getVatRate(Shop $shop): string
    {
        $taxCategory = TaxCategory::where('type', TaxCategoryTypeEnum::STANDARD)
            ->where('country_id', $shop->country_id)
            ->where('status', true)
            ->orderByDesc('id')
            ->first();

        if (!$taxCategory) {
            return '';
        }

        return rtrim(rtrim(number_format($taxCategory->rate * 100, 2, '.', ''), '0'), '.');
    }

    /**
     * Zentrada recommends 1000 x 1000 pixel images, so the 1200 pixel gallery size is sent rather
     * than the 600 pixel one the website shows.
     */
    private function extractImages(mixed $webImages): array
    {
        if (is_string($webImages)) {
            $webImages = json_decode($webImages, true);
        }

        $images = [];
        foreach (Arr::get($webImages, 'all', []) as $image) {
            $url = Arr::get($image, 'gallery.original_2x')
                ?? Arr::get($image, 'original.original')
                ?? Arr::get($image, 'gallery.original');

            if ($url) {
                $images[] = $url;
            }

            if (count($images) === self::MAX_IMAGES) {
                break;
            }
        }

        return $images;
    }

    public function asController(ActionRequest $request): StreamedResponse
    {
        $this->shop = $request->website->shop;

        return $this->handle($this->shop);
    }
}
