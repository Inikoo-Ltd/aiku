<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-47m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\CRM\WebUser\Retina\Json;

use App\Actions\IrisAction;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use App\Enums\Web\Webpage\WebpageTypeEnum;

class GetRedirectUrl extends IrisAction
{
    use AsController;


    public function handle($modelData): array
    {
        $ref_page = null;

        // default = storefront
        $storefront = Webpage::where('type', WebpageTypeEnum::STOREFRONT)
            ->where('state', WebpageStateEnum::LIVE)
            ->where('website_id', $this->website->id)
            ->first();

        $retinaHome = $storefront
            ? ShowIrisWebpage::make()->getEnvironmentUrl($storefront->canonical_url)
            : '';

        // ref page always wins
        if (Arr::has($modelData, 'ref')) {
            $ref_page = Arr::get($modelData, 'ref');

            if ($ref_page && is_numeric($ref_page)) {
                $webpage = Webpage::where('id', $ref_page)
                    ->where('website_id', $this->website->id)
                    ->where('state', WebpageStateEnum::LIVE)
                    ->first();

                if ($webpage) {
                    $retinaHome = ShowIrisWebpage::make()
                        ->getEnvironmentUrl($webpage->canonical_url);
                }
            }
        }

        // landing page only used if:
        // - user logged in
        // - no valid ref page found
        if (
            auth()->check() &&
            !$ref_page
        ) {
            $landingPage = Webpage::where('type', WebpageTypeEnum::LANDING_PAGE)
                ->where('state', WebpageStateEnum::LIVE)
                ->where('website_id', $this->website->id)
                ->first();

            if ($landingPage) {
                $retinaHome = ShowIrisWebpage::make()
                    ->getEnvironmentUrl($landingPage->canonical_url);
            }
        }

        return [
            'ref_page'     => $ref_page,
            'redirect_url' => $retinaHome,
            'redirected'   => $retinaHome !== '',
        ];
    }

    public function rules(): array
    {
        return [
            'ref' => ['sometimes'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
