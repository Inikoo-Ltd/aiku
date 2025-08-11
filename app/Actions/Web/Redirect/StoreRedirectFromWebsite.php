<?php

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Web\WebsiteTabsEnum;
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
    public function handle(Website $website, array $modelData): Redirect
    {
        data_set($modelData, 'group_id', $website->group_id);
        data_set($modelData, 'organisation_id', $website->organisation_id);
        data_set($modelData, 'shop_id', $website->shop_id);
        data_set($modelData, 'website_id', $website->id);

        data_set($modelData, 'type', RedirectTypeEnum::PERMANENT->value); // Todo: check this

        $fromUrl = Arr::get($modelData, 'from_url', '');

        if (!str_starts_with($fromUrl, '/')) {
            $fromUrl = '/' . ltrim($fromUrl, '/');
        }

        $url = 'https://' . $website->domain . $fromUrl;
        $toUrl = Arr::pull($modelData, 'to_url');

        data_set($modelData, 'from_url', $url);
        data_set($modelData, 'from_path', $fromUrl);
        data_set($modelData, 'to_webpage_id', $toUrl);

        return Redirect::create($modelData);
    }

    public function rules(): array
    {
        return [
            'from_url'                => ['required', 'string', 'max:2048'],
            'to_url' => [
                'required',
                Rule::exists(Webpage::class, 'id')->where('website_id', $this->shop->website->id)->where('state', WebpageStateEnum::LIVE),
            ],
        ];
    }

    public function htmlResponse(Redirect $redirect): RedirectResponse
    {
        if ($redirect->shop->type == ShopTypeEnum::FULFILMENT) {
            return FacadesRedirect::route(
                'grp.org.fulfilments.show.web.websites.show',
                [
                    'organisation' => $redirect->organisation->slug,
                    'fulfilment' => $redirect->shop->fulfilment->slug,
                    'website' => $redirect->website->slug,
                    'tab' => WebsiteTabsEnum::REDIRECTS->value
                ]
            );
        }

        return FacadesRedirect::route(
            'grp.org.shops.show.web.websites.show',
            [
                'organisation' => $redirect->organisation->slug,
                'shop' => $redirect->shop->slug,
                'website' => $redirect->website->slug,
                'tab' => WebsiteTabsEnum::REDIRECTS->value
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
