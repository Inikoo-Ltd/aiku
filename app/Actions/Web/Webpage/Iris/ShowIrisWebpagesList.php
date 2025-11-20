<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\Iris;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowIrisWebpagesList
{
    use AsController;

    public function handle(Website $website, $mode = 'all'): StreamedResponse
    {
        $domain = 'https://www.'.$website->domain.'/';

        $callback = function () use ($website, $domain, $mode) {
            // Build the base query
            $query = DB::table('webpages')
                ->select(['webpages.id', 'webpages.url', 'webpages.canonical_url'])
                ->where('webpages.website_id', $website->id)
                ->whereNull('webpages.deleted_at') // only non-deleted pages
                ->where('webpages.state', WebpageStateEnum::LIVE->value)
                ->orderBy('webpages.id');

            // Mode-specific adjustments
            if ($mode == 'products') {
                $query = DB::table('webpages')
                    ->leftJoin('products', 'webpages.model_id', '=', 'products.id')
                    ->leftJoin('assets', 'products.asset_id', '=', 'assets.id')
                    ->leftJoin('asset_sales_intervals', 'assets.id', '=', 'asset_sales_intervals.asset_id')
                    ->select(['webpages.id', 'webpages.url', 'webpages.canonical_url','sales_1q'])
                    ->where('webpages.website_id', $website->id)
                    ->whereNull('webpages.deleted_at')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value)
                    ->where('products.state', ProductStateEnum::ACTIVE->value)
                    ->where('webpages.sub_type', 'product')
                    ->orderBy('sales_1q', 'desc')
                    ->limit(2500);
            } elseif ($mode == 'families') {
                $query = DB::table('webpages')
                    ->leftJoin('product_categories', 'webpages.model_id', '=', 'product_categories.id')
                    ->leftJoin('product_category_sales_intervals', 'product_categories.id', '=', 'product_category_sales_intervals.product_category_id')
                    ->select(['webpages.id', 'webpages.url', 'webpages.canonical_url','sales_1q'])
                    ->where('product_categories.state', ProductCategoryStateEnum::ACTIVE->value)
                    ->where('webpages.website_id', $website->id)
                    ->whereNull('webpages.deleted_at')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value)
                    ->where('webpages.sub_type', 'family')
                    ->orderBy('sales_1q', 'desc')
                    ->limit(750);

            } elseif ($mode == 'base') {
                $query->whereNotIn('webpages.sub_type', ['product', 'family']);
            }


            // Iterate using a cursor (no chunkById)
            foreach ($query->get() as $row) {
                print "$row->canonical_url\n";
//                $url = $domain . $row->url;
//                if ($url != $row->canonical_url) {
//                    print $url . "\n";
//                }
                // Flush output buffers periodically to stream to the client
                if (function_exists('flush')) {
                    @flush();
                }
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
            }
        };

        return response()->stream($callback, 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function asController(ActionRequest $request): StreamedResponse
    {
        /** @var Website $website */
        $website = $request->get('website');

        return $this->handle($website);
    }

    public function base(ActionRequest $request): StreamedResponse
    {
        /** @var Website $website */
        $website = $request->get('website');

        return $this->handle($website, 'base');
    }

    public function products(ActionRequest $request): StreamedResponse
    {
        /** @var Website $website */
        $website = $request->get('website');

        return $this->handle($website, 'products');
    }

    public function families(ActionRequest $request): StreamedResponse
    {
        /** @var Website $website */
        $website = $request->get('website');

        return $this->handle($website, 'families');
    }
}
