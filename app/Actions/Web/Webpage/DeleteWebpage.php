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
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateRedirects;
use App\Actions\Web\Webpage\Luigi\DeleteReindexWebpageLuigiData;
use App\Actions\Web\Website\HydrateRedirect;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private Webpage $webpage;

    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, bool $forceDelete = false, array $modelData = []): Webpage
    {
        if ($forceDelete) {
            $webpage = DB::transaction(function () use ($webpage) {
                DB::table('web_block_histories')->where('webpage_id', $webpage->id)->delete();
                DB::table('webpage_has_linked_webpages')->where('webpage_id', $webpage->id)->delete();
                DB::table('web_block_has_external_link')->where('webpage_id', $webpage->id)->delete();
                DB::table('webpage_time_series')->where('webpage_id', $webpage->id)->delete();
                DB::table('webpage_stats')->where('webpage_id', $webpage->id)->delete();
                DB::table('redirects')->where('to_webpage_id', $webpage->id)->delete();
                DB::table('redirects')->where('from_webpage_id', $webpage->id)->update(['from_webpage_id' => null]);
                DB::table('model_has_web_blocks')->where('webpage_id', $webpage->id)->delete();
                if ($webpage->model_type == 'Product') {
                    DB::table('products')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                }
                if ($webpage->model_type == 'ProductCategory') {
                    DB::table('product_categories')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                }
                if ($webpage->model_type == 'Collection') {
                    DB::table('collections')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
                }
                $webpage->images()->detach();
                $webpage->forceDelete();

                return $webpage;
            });
        } else {

            $redirect = Arr::pull($modelData, 'redirects');

            $webpage->delete();

            if ($webpage->model_type == 'Product') {
                DB::table('products')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
            }
            if ($webpage->model_type == 'ProductCategory') {
                DB::table('product_categories')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
            }
            if ($webpage->model_type == 'Collection') {
                DB::table('collections')->where('webpage_id', $webpage->id)->update(['webpage_id' => null]);
            }

            if ($redirect) {
                DB::table('redirects')->where('to_webpage_id', $webpage->id)->delete();
                DB::table('redirects')->where('from_path', $webpage->url)->delete();

                StoreRedirect::make()->action($webpage, [
                    'type'              => RedirectTypeEnum::PERMANENT,
                    'to_webpage_id'     => $redirect
                ]);

                HydrateRedirect::run($webpage);

                $redirectedWebpage = Webpage::find($redirect);
                if ($redirectedWebpage) {
                    WebpageHydrateRedirects::run($redirectedWebpage);
                }
            }
        }

        DeleteReindexWebpageLuigiData::dispatch($webpage);

        return $webpage;
    }

    public function rules()
    {
        return [
            'redirects'  => [
                'sometimes',
                Rule::exists(Webpage::class, 'id')->where('website_id', $this->webpage->website->id)->where('state', WebpageStateEnum::LIVE),
            ],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Webpage $webpage, bool $forceDelete = false): Webpage
    {
        $this->webpage = $webpage;
        return $this->handle($webpage, $forceDelete);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($webpage->shop, $request);

        $forceDelete = $request->boolean('force_delete');

        return $this->handle($webpage, $forceDelete, $this->validatedData);
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
