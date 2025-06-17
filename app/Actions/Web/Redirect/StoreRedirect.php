<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect as FacadesRedirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRedirect extends OrgAction
{
    public function handle(Webpage $webpage, array $modelData): Redirect
    {
        data_set($modelData, 'group_id', $webpage->group_id);
        data_set($modelData, 'organisation_id', $webpage->organisation_id);
        data_set($modelData, 'shop_id', $webpage->shop_id);
        data_set($modelData, 'website_id', $webpage->website_id);

        $url = 'https:://'.$webpage->website->domain . '/' . $webpage->url;

        data_set($modelData, 'from_url', $url);
        data_set($modelData, 'from_path', $webpage->url);

        return $webpage->redirectedTo()->create($modelData);

    }

    public function rules(): array
    {
        return [
            'type'                     => ['required', Rule::enum(RedirectTypeEnum::class)],
            'to_webpage_id' => [
                'required',
                Rule::exists(Webpage::class, 'id')->where('website_id', $this->shop->website->id)->where('state', WebpageStateEnum::LIVE),
            ],
        ];
    }

    public function htmlResponse(Redirect $redirect): RedirectResponse
    {
        if ($redirect->shop->type == ShopTypeEnum::FULFILMENT) {
            return FacadesRedirect::route(
                'grp.org.fulfilments.show.web.webpages.show',
                [
                'organisation' => $redirect->organisation->slug,
                'fulfilment' => $redirect->shop->fulfilment->slug,
                'website' => $redirect->website->slug,
                'webpage' => $redirect->webpage->slug,
                'tab' => WebpageTabsEnum::REDIRECTS->value
            ]
            );
        }

        return FacadesRedirect::route(
            'grp.org.shops.show.web.webpages.show',
            [
            'organisation' => $redirect->organisation->slug,
            'shop' => $redirect->shop->slug,
            'website' => $redirect->website->slug,
            'webpage' => $redirect->webpage->slug,
            'tab' => WebpageTabsEnum::REDIRECTS->value
        ]
        );
    }

    public function action(Webpage $webpage, array $modelData): Redirect
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }

    public function inWebpage(Webpage $webpage, ActionRequest $request): Redirect
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }


}
