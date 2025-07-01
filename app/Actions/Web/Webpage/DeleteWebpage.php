<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;


    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, bool $forceDelete = false): Webpage
    {
        if ($forceDelete) {
            $webpage = DB::transaction(function () use ($webpage) {
                DB::table('webpage_has_linked_webpages')->where('webpage_id', $webpage->id)->delete();
                DB::table('web_block_has_external_link')->where('webpage_id', $webpage->id)->delete();
                DB::table('webpage_time_series')->where('webpage_id', $webpage->id)->delete();
                DB::table('webpage_stats')->where('webpage_id', $webpage->id)->delete();
                DB::table('redirects')->where('to_webpage_id', $webpage->id)->delete();
                DB::table('redirects')->where('from_webpage_id', $webpage->id)->update(['from_webpage_id' => null]);
                DB::table('model_has_web_blocks')->where('webpage_id', $webpage->id)->delete();
                DB::table('products')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                DB::table('product_categories')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                DB::table('collections')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                $webpage->forceDelete();

                return $webpage;
            });
        } else {
            $webpage->delete();
        }

        WebpageRecordSearch::run($webpage);

        return $webpage;
    }

    /**
     * @throws \Throwable
     */
    public function action(Webpage $webpage, bool $forceDelete = false): Webpage
    {
        return $this->handle($webpage, $forceDelete);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($webpage->organisation, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($webpage, $forceDelete);
    }


    public function htmlResponse(Webpage $webpage): RedirectResponse
    {
        return redirect()->route(
            'grp.org.shops.show.web.webpages.index',
            [
                'organisation' => $webpage->organisation->slug,
                'shop'         => $webpage->shop->slug,
                'website'      => $webpage->website->slug,
                'webpage'      => $webpage->slug
            ]
        );
    }
}
