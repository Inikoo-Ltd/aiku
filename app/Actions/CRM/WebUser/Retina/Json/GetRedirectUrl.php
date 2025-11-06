<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-47m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\CRM\WebUser\Retina\Json;

use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Http\Request;

class GetRedirectUrl
{
    use AsController;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Request $request): string
    {
        $retinaHome = '';
        
        $ref_page = request()->get('ref');  // '239984'
        
        if ($ref_page && is_numeric($ref_page)) {
            $webpage = Webpage::where('id', $ref_page)->where('website_id', $request->get('website')->id)
                ->where('state', WebpageStateEnum::LIVE)->first();
            if ($webpage) {
                $retinaHome = ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);
            }
        }

        return $retinaHome;  // "https://xxx.test/aaa/bbb/ccc-02"
    }

    public function jsonResponse(): string
    {
        return $this->handle(request());
    }

}
