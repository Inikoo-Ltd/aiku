<?php

/*
 * author Louis Perez
 * created on 28-04-2026-11h-01m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateRedirects;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect as FacadesRedirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRedirectFromWebpage extends OrgAction
{
    private Webpage $webpage;

    public function handle(Webpage $webpage, array $modelData): Redirect
    {
        $website = $webpage->website;
        data_set($modelData, 'group_id', $webpage->group_id);
        data_set($modelData, 'organisation_id', $webpage->organisation_id);
        data_set($modelData, 'shop_id', $webpage->shop_id);
        data_set($modelData, 'website_id', $website->id);

        data_set($modelData, 'type', RedirectTypeEnum::PERMANENT->value); // Todo: check this

        $fromUrl = Arr::get($modelData, 'from_url', '');

        if (str_starts_with($fromUrl, '/')) {
            $fromUrl = ltrim($fromUrl, '/');
        }

        $url = 'https://' . $website->domain . '/' . $fromUrl;

        data_set($modelData, 'from_url', $url);
        data_set($modelData, 'from_path', $fromUrl);
        data_set($modelData, 'to_webpage_id', $webpage->id);

        $this->disableReload = Arr::pull($modelData, 'disableReload', false);

        $redirect = Redirect::create($modelData);

        WebpageHydrateRedirects::run($webpage);

        return $redirect;
    }

    public function rules(): array
    {
        return [
            'type'                     => [
                'required',
                Rule::enum(RedirectTypeEnum::class)
            ],
            'from_url'                => [
                'required',
                'string',
                'max:2048',
                Rule::unique(Redirect::class, 'from_path')
                    ->where(fn ($query) => $query->where('website_id', $this->shop->website->id)),
            ],
            'disableReload'            => [
                'sometimes',
                'boolean'
            ]
        ];
    }

    public function htmlResponse(Redirect $redirect): RedirectResponse
    {
        if ($this->disableReload) {
            return redirect()->back()->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Created new redirect route for this webpage'),
            ]);
        }

        if ($redirect->shop->type == ShopTypeEnum::FULFILMENT) {
            return FacadesRedirect::route(
                'grp.org.fulfilments.show.web.webpages.show',
                [
                    'organisation'  => $redirect->organisation->slug,
                    'fulfilment'    => $redirect->shop->fulfilment->slug,
                    'website'       => $redirect->website->slug,
                    'webpage'       => $this->webpage->slug,
                    'tab'           => WebpageTabsEnum::REDIRECTS->value,
                ]
            );
        }

        return FacadesRedirect::route(
            'grp.org.shops.show.web.webpages.show',
            [
                'organisation'  => $this->webpage->organisation->slug,
                'shop'          => $this->webpage->shop->slug,
                'website'       => $this->webpage->website->slug,
                'webpage'       => $this->webpage->slug,
                'tab'           => WebpageTabsEnum::REDIRECTS->value,
            ]
        );
    }

    public function asController(Webpage $webpage, ActionRequest $request): Redirect
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}
