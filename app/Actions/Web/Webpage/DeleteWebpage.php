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


    public function handle(Webpage $webpage, bool $forceDelete = false): Webpage
    {
        if ($forceDelete) {
            DB::table('webpage_has_linked_webpages')->where('webpage_id', $webpage->id)->delete();
            DB::table('web_block_has_external_link')->where('webpage_id', $webpage->id)->delete();
            DB::table('webpage_time_series')->where('webpage_id', $webpage->id)->delete();
            DB::table('webpage_stats')->where('webpage_id', $webpage->id)->delete();
            DB::table('redirects')->where('webpage_id', $webpage->id)->delete();
            DB::table('model_has_web_blocks')->where('webpage_id', $webpage->id)->delete();
            $webpage->forceDelete();
        } else {
            $webpage->delete();
        }

        WebpageRecordSearch::run($webpage);

        return $webpage;
    }

    public function action(Webpage $webpage, bool $forceDelete = false): Webpage
    {
        return $this->handle($webpage, $forceDelete);
    }

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
