<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:30:40 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\UI\WithFavicon;
use App\Actions\Traits\UI\WithLogo;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Website\Search\WebsiteRecordSearch;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\Web\WebsiteResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\Website;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebsite extends OrgAction
{
    use WithActionUpdate;
    // use WithWebAuthorisation;
    use WithLogo;
    use WithFavicon;

    private Website $website;


    public function handle(Website $website, array $modelData): Website
    {
        $website = $this->processWebsiteLogo($modelData, $website);
        $website = $this->processWebsiteFavicon($modelData, $website);
        data_forget($modelData, 'image');
        data_forget($modelData, 'favicon');

        if (Arr::has($modelData, "google_tag_id")) {
            data_set($modelData, "settings.google_tag_id", Arr::pull($modelData, "google_tag_id"));
        }

        if (Arr::has($modelData, "catalogue_template")) {
            data_set($modelData, "settings.catalogue_template", Arr::pull($modelData, "catalogue_template"));
        }

        if (Arr::has($modelData, "luigisbox_tracker_id")) {
            data_set($modelData, "settings.luigisbox.tracker_id", Arr::pull($modelData, "luigisbox_tracker_id"));
        }

        if (Arr::has($modelData, "luigisbox_script_lbx")) {
            data_set($modelData, "settings.luigisbox.script_lbx", Arr::pull($modelData, "luigisbox_script_lbx"));
        }

        $website = $this->update($website, $modelData, ['data', 'settings']);
        WebsiteRecordSearch::run($website);

        return $website;
    }


    public function rules(): array
    {
        $rules = [
            'domain'        => [
                'sometimes',
                'required',
                'ascii',
                'lowercase',
                'max:255',
                new IUnique(
                    table: 'websites',
                    extraConditions: [
                        [
                            'column' => 'group_id',
                            'value'  => $this->organisation->group_id
                        ],
                        [
                            'column'    => 'status',
                            'operation' => '=',
                            'value'     => true
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->website->id
                        ],
                    ]
                )
            ],
            'code'          => [
                'sometimes',
                'required',
                'ascii',
                'lowercase',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'websites',
                    extraConditions: [

                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->website->id
                        ],
                    ]
                ),

            ],
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'launched_at'   => ['sometimes', 'date'],
            'state'         => ['sometimes', Rule::enum(WebsiteStateEnum::class)],
            'status'        => ['sometimes', 'boolean'],
            'google_tag_id' => [
                'sometimes',
                'string',
                'regex:/^(G-[A-Z0-9]{10}|UA-\d{4,10}-\d{1,4}|GTM-[A-Z0-9]{7})$/'
            ],
            'catalogue_template' => ['sometimes', 'array'],
            'luigisbox_tracker_id' => [
                'sometimes',
                'string',
                'regex:/^\d{6}-\d{6}$/'
            ],
            'luigisbox_script_lbx' => [
                'sometimes',
                'string',
            ],
            // 'luigisbox_api_key' => ['sometimes', 'string'],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'favicon'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ]
        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['domain']          = [
                'sometimes',
                'required',
                'ascii',
                'lowercase',
                'max:255',
                new IUnique(
                    table: 'websites',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id
                        ],
                        [
                            'column'    => 'status',
                            'operation' => '=',
                            'value'     => true
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->website->id
                        ],
                    ]
                )
            ];

        }

        return $rules;
    }

    public function action(Website $website, array $modelData, int $hydratorsDelay = 0, $strict = true, bool $audit = true): Website
    {
        if (!$audit) {
            Website::disableAuditing();
        }
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->website        = $website;

        $this->initialisation($website->organisation, $modelData);

        return $this->handle($website, $this->validatedData);
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->scope   = $website->shop;
        $this->website = $website;
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, $this->validatedData);
    }


    public function inFulfilment(Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->scope   = $fulfilment;
        $this->website = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website, $this->validatedData);
    }

    public function jsonResponse(Website $website): WebsiteResource
    {
        return new WebsiteResource($website);
    }
}
