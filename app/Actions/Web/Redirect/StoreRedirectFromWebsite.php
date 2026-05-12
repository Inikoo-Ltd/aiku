<?php

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Actions\Web\Redirect\Traits\WithStoreRedirect;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateRedirects;
use App\Actions\Web\Website\BreakWebsiteCache;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect as FacadesRedirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRedirectFromWebsite extends OrgAction
{
    use WithStoreRedirect;

    public function handle(Website $website, array $modelData): Redirect
    {
        data_set($modelData, 'group_id', $website->group_id);
        data_set($modelData, 'organisation_id', $website->organisation_id);
        data_set($modelData, 'shop_id', $website->shop_id);
        data_set($modelData, 'website_id', $website->id);

        data_set($modelData, 'type', RedirectTypeEnum::PERMANENT->value);

        $toUrl = Arr::pull($modelData, 'to_url');

        data_set($modelData, 'to_webpage_id', $toUrl);

        $redirect = Redirect::create($modelData);

        $redirectedWebpage = Webpage::find($toUrl);
        if ($redirectedWebpage) {
            WebpageHydrateRedirects::run($redirectedWebpage);
        }
        BreakWebsiteCache::dispatch($website)->delay(now()->addMinute(1));

        return $redirect;
    }

    public function rules(): array
    {
        return [
            'from_path'     => [
                'required',
                'string',
                'max:2048',
                Rule::unique(Webpage::class, 'url')
                    ->where(fn ($query) => $query->where('website_id', $this->shop->website->id)->where('state', 'live')->whereNull('deleted_at')),
                Rule::unique(Redirect::class, 'from_path')
                    ->where(fn ($query) => $query->where('website_id', $this->shop->website->id))
            ],
            'from_url'      => [
                'required',
                'string',
                'max:2048',
                Rule::unique(Redirect::class, 'from_url')
                    ->where(fn ($query) => $query->where('website_id', $this->shop->website->id)),
            ],
            'to_url'        => [
                'required',
                Rule::exists(Webpage::class, 'id')
                    ->where(
                        fn ($query) => $query
                        ->where('website_id', $this->shop->website->id)
                        ->where('state', WebpageStateEnum::LIVE)
                    ),
            ],
        ];
    }

    public function htmlResponse(Redirect $redirect): RedirectResponse
    {
        if ($redirect->shop->type == ShopTypeEnum::FULFILMENT) {
            return FacadesRedirect::route(
                'grp.org.fulfilments.show.web.redirect.index',
                [
                    'organisation' => $redirect->organisation->slug,
                    'fulfilment' => $redirect->shop->fulfilment->slug,
                    'website' => $redirect->website->slug,
                ]
            );
        }

        return FacadesRedirect::route(
            'grp.org.shops.show.web.redirect.index',
            [
                'organisation' => $redirect->organisation->slug,
                'shop' => $redirect->shop->slug,
                'website' => $redirect->website->slug,
            ]
        );
    }

    public function action(Website $website, array $modelData): Redirect
    {
        $this->asAction       = true;
        $this->initialisationFromShop($website->shop, $modelData);

        return $this->handle($website, $this->validatedData);
    }

    public function asController(Website $website, ActionRequest $request): Redirect
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, $this->validatedData);
    }
}
