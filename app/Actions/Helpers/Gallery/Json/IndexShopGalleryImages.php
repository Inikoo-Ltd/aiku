<?php

namespace App\Actions\Helpers\Gallery\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Media;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class IndexShopGalleryImages extends OrgAction
{
    private const int PER_PAGE = 48;

    /**
     * The shop's website logo, favicon and header images.
     */
    public function logos(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        $website = $shop->website;

        $labelledImages = DB::query()
            ->fromSub(
                DB::table('websites')->where('id', $website?->id ?? 0)->whereNotNull('logo_id')->selectRaw("logo_id as media_id, ? as label", [__('Website logo')])
                    ->unionAll(DB::table('websites')->where('id', $website?->id ?? 0)->whereNotNull('favicon_id')->selectRaw("favicon_id as media_id, ? as label", [__('Website favicon')]))
                    ->unionAll(
                        DB::table('model_has_media')
                            ->where('model_type', 'Website')
                            ->where('model_id', $website?->id ?? 0)
                            ->where('scope', 'header')
                            ->selectRaw("media_id, ? as label", [__('Website header')])
                    ),
                'labelled_images'
            );

        return $this->paginateLabelledImages($labelledImages, $request->input('filter.global'));
    }

    /**
     * The main image of the shop's products, families, departments and collections that are still on sale.
     */
    public function catalogue(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        $onSaleStates = ['active', 'discontinuing'];

        $labelledImages = DB::query()
            ->fromSub(
                DB::table('products')
                    ->where('shop_id', $shop->id)->where('is_main', true)->whereNull('deleted_at')->whereIn('state', $onSaleStates)->whereNotNull('image_id')
                    ->selectRaw("image_id as media_id, concat(code, ' ', name) as label")
                    ->unionAll(
                        DB::table('product_categories')
                            ->where('shop_id', $shop->id)->whereNull('deleted_at')->whereIn('state', $onSaleStates)->whereNotNull('image_id')
                            ->selectRaw("image_id as media_id, concat(code, ' ', name) as label")
                    )
                    ->unionAll(
                        DB::table('collections')
                            ->where('shop_id', $shop->id)->whereNull('deleted_at')->where('state', 'active')->whereNotNull('image_id')
                            ->selectRaw("image_id as media_id, concat(code, ' ', name) as label")
                    ),
                'labelled_images'
            );

        return $this->paginateLabelledImages($labelledImages, $request->input('filter.global'));
    }

    private function paginateLabelledImages(Builder $labelledImages, ?string $search): LengthAwarePaginator
    {
        if ($search) {
            $labelledImages->where('label', 'ilike', '%'.addcslashes($search, '%_\\').'%');
        }

        $images = DB::query()
            ->fromSub($labelledImages->groupBy('media_id')->selectRaw('media_id, min(label) as label'), 'images')
            ->orderBy('label')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $medias = Media::whereIn('id', $images->pluck('media_id'))->get()->keyBy('id');

        return $images->setCollection(
            $images->getCollection()
                ->filter(fn ($image) => $medias->has($image->media_id))
                ->map(function ($image) use ($medias) {
                    $media       = $medias->get($image->media_id);
                    $media->name = trim($image->label);

                    return $media;
                })
                ->values()
        );
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, route: array{name: string, parameters: array<string, string>}}>
     */
    public static function categories(?Shop $shop): array
    {
        if (!$shop) {
            return [];
        }

        return [
            [
                'key'   => 'logos',
                'label' => __('Logos'),
                'icon'  => 'fal fa-copyright',
                'route' => ['name' => 'grp.json.shop.gallery.logos', 'parameters' => ['shop' => $shop->slug]],
            ],
            [
                'key'   => 'catalogue',
                'label' => __('Catalogue images'),
                'icon'  => 'fal fa-books',
                'route' => ['name' => 'grp.json.shop.gallery.catalogue', 'parameters' => ['shop' => $shop->slug]],
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $shopId = $this->shop->id;

        return $request->user()->authTo([
            "shop-admin.$shopId",
            "marketing.$shopId.edit",
            "supervisor-marketing.$shopId",
            "crm.$shopId.edit",
            "crm.$shopId.prospects.edit",
            "web.$shopId.edit",
        ]);
    }

    public function jsonResponse(LengthAwarePaginator $medias): AnonymousResourceCollection
    {
        return ImageResource::collection($medias);
    }
}
