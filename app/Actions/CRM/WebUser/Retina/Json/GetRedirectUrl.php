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

class GetRedirectUrl extends IrisAction
{
    use AsController;


    public function handle($modelData): array
    {

        $retinaHome = '';
        $ref_page   = null;
        if (Arr::has($modelData, 'ref')) {
            $ref_page = Arr::get($modelData, 'ref');

            if ($ref_page && is_numeric($ref_page)) {
                $webpage = Webpage::where('id', $ref_page)->where('website_id', $this->website->id)
                    ->where('state', WebpageStateEnum::LIVE)->first();
                if ($webpage) {
                    $retinaHome = ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);
                }
            }
        }


        return [
            'ref_page'     => $ref_page,
            'redirect_url' => $retinaHome,
            'redirected'   => !($retinaHome == ''),
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
