<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\Iris;

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
            $chunkSize = 1000;
            $query     = DB::table('webpages')
                ->select(['id', 'url', 'canonical_url'])
                ->where('website_id', $website->id)
                ->whereNull('deleted_at') // only non-deleted pages
                ->where('state', WebpageStateEnum::LIVE->value)
                ->orderBy('id');

            if ($mode == 'products') {
                $query->where('sub_type', 'product');
            } elseif ($mode == 'families') {
                $query->where('sub_type', 'family');
            } elseif ($mode == 'base') {
                $query->whereNotIn('sub_type', ['product', 'family']);
            }

            $query->chunkById($chunkSize, function ($rows) use ($domain) {
                foreach ($rows as $row) {
                    print $row->canonical_url."\n";
                    $url = $domain.$row->url;

                    if ($url != $row->canonical_url) {
                        print $url."\n";
                    }
                }
            }, 'id');
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
