<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 14:27:36 Central Indonesia Time, Shanghai, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Web\WebBlock\Concerns\WithIrisImageVariants;
use App\Enums\Catalogue\ProductCategory\FamilyCustomizeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\FamilyStorageConditionEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasCardWebImages;
use Illuminate\Support\Arr;
use App\Helpers\WebsiteLanguage;

class WebBlockFamilyResource extends JsonResource
{
    use HasCardWebImages;
    use HasSelfCall;
    use WithIrisImageVariants;

    public const array SRCSET_WIDTHS = [360, 720, 1440];

    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this->resource;

        return [
            'slug'                      => $family->slug,
            'code'                      => $family->code,
            'name'                      => WebsiteLanguage::text($family, 'name'),
            'description'               => WebsiteLanguage::text($family, 'description'),
            'description_title'         => WebsiteLanguage::text($family, 'description_title'),
            'description_extra'         => WebsiteLanguage::text($family, 'description_extra'),
            'id'                        => $family->id,
            'description_image'         => collect(Arr::get($family->web_images, 'description', []))->map(fn ($slot) => $this->getResizedSlot($slot))->filter()->all(),
            'description_video'         => $family->desc_video_url,
            'extra_description_image'   => collect(Arr::get($family->web_images, 'extraDescription', []))->map(fn ($slot) => $this->getResizedSlot($slot))->filter()->all(),
            'url'                       => $family->webpage?->url,
            'offers_data'               => $family->offers_data,
            'tags'                      => $family->tradeUnitFamily?->tags()->limit(3)->get()->map(fn ($tag) => ['name' => WebsiteLanguage::tagName($tag), 'web_image' => $this->getPictureFormats($tag->web_image)])->all(),
            ...$this->getTabsData($family)
        ];
    }

    public static function getTabsData(ProductCategory $family): array
    {
        $labelingGuide = null;

        $tradeUnitFamily = $family->tradeUnitFamily;

        if ($family->follow_tuf_labeling_guide && $family->tradeUnitFamily) {
            $labelingGuide = $tradeUnitFamily->labelingGuide();
        } else {
            $labelingGuide = $family->labelingGuide();
        }

        return [
            'customize_option'          => FamilyCustomizeEnum::rows($family->customize_option),
            'labeling_guide'            => $labelingGuide ? [
                'label' => $labelingGuide->name,
                'route' => [
                    'name'          => 'iris.attach.download',
                    'parameters'    => [
                        'media'    => $labelingGuide->ulid
                    ],
                ]
            ] : null,
            'storage_option'            => [
                'storage_conditions'    => FamilyStorageConditionEnum::rows(data_get($family->storage_option, 'storage_conditions', [])),
                'storage_temperature'   => data_get($family->storage_option, 'storage_temperature', ''),
                'storage_guidelines'    => data_get($family->storage_option, 'storage_guidelines', []),
            ],
            'is_aroma_organisation'     => $family->organisation?->slug === 'aroma',
            'marketing_material_route'  => [
                'name'          => 'iris.catalogue.feeds.product_category.download_img',
                'parameters'    => [
                    'productCategory'   => $family->slug,
                    'type'              => 'products_images'
                ]
            ],
            'marketing_images'          => self::getMarketingImages($family),
            'faq'                       => WebsiteLanguage::faq($family->webpage?->website_id ?? $family->shop?->website?->id, $family->faq),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function getMarketingImages(ProductCategory $family): array
    {
        return Product::where(function ($query) use ($family) {
            $query->where('family_id', $family->id);
            if ($family->department_id) {
                $query->orWhere('department_id', $family->department_id);
            }
        })
            ->where('is_for_sale', true)
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->whereNotNull('web_images->main->gallery')
            ->orderByRaw('family_id = ? desc', [$family->id])
            ->orderByDesc('is_golden_product')
            ->orderBy('id')
            ->limit(5)
            ->pluck('web_images')
            ->map(fn (?array $webImages) => Arr::only(Arr::get($webImages, 'main.gallery') ?? [], ['avif', 'avif_2x', 'original', 'original_2x']))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Description image slots store whatever urls existed when the family was saved,
     * including unresized originals; rebuild them capped at 1440px.
     *
     * @return array<string, string>|null
     */
    private function getResizedSlot(mixed $slot): ?array
    {
        if (!is_array($slot)) {
            return null;
        }

        $originalUrl = Arr::get($slot, 'original');
        if (is_array($originalUrl)) {
            $originalUrl = Arr::get($originalUrl, 'original');
        }

        $media = is_string($originalUrl) ? $this->findMediaFromImgProxyUrl($originalUrl) : null;
        if (!$media) {
            return $slot;
        }

        $formats = [
            'original' => GetImgProxyUrl::run($media->getImage()->resize(1440, 1440)),
        ];
        if (in_array('avif', config('img-proxy.formats')) && !$media->is_animated) {
            $formats['avif'] = GetImgProxyUrl::run($media->getImage()->resize(1440, 1440)->extension('avif'));
        }

        $resized = [
            'original' => $formats,
            'srcset'   => $this->getWidthSrcSets($media, self::SRCSET_WIDTHS),
        ];
        if (Arr::has($slot, 'alt')) {
            $resized['alt'] = Arr::get($slot, 'alt');
        }

        return $resized;
    }
}
