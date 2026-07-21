<?php

/*
 * Author Louis Perez
 * Created on 20-07-2026-16h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetWebpageOfflineBulk extends OrgAction
{
    public function handle(Website $website, array $modelData)
    {
        $webpages = Webpage::whereIn('id', data_get($modelData, 'webpages.*.id', []))->get();
        $redirectedWebpage = Webpage::find(data_get($modelData, 'redirect_id'));

        if ($redirectedWebpage->website_id != $website->id) {
            abort(422, __('The selected redirect webpage does not belong to this website'));
        }

        foreach ($webpages as $webpage) {
            UpdateWebpage::make()->action($webpage, [
                'state_data'    => [
                    'state'                 => WebpageStateEnum::CLOSED->value,
                    'redirect_webpage_id'   => $redirectedWebpage->id,
                ]
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'webpages'          => ['required', 'array'],
            'webpages.*.id'     => ['required', Rule::exists('webpages', 'id')],
            'redirect_id'       => ['required', Rule::exists('webpages', 'id')],
        ];
    }

    public function asController(Website $website, ActionRequest $request)
    {
        $this->initialisationFromShop($website->shop, $request);

        $this->handle($website, $this->validatedData);
    }
}
