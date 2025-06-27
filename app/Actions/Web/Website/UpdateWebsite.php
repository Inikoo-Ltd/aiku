<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:30:40 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebsite extends OrgAction
{
    use WithActionUpdate;
    use WithWebEditAuthorisation;
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

        if (Arr::has($modelData, "luigisbox_private_key")) {
            data_set($modelData, "settings.luigisbox.private_key", Arr::pull($modelData, "luigisbox_private_key"));
        }

        if (Arr::has($modelData, "return_policy")) {
            data_set($modelData, "settings.return_policy", Arr::pull($modelData, "return_policy"));
        }

        if (Arr::has($modelData, "script_website")) {
            data_set($modelData, "settings.script_website.header", Arr::pull($modelData, "script_website"));
        }

        $website = $this->update($website, $modelData, ['data', 'settings']);

        $changes = Arr::except($website->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, [
            'code',
            'name',
            'domain',
            'type',
            'state',
        ])) {
            WebsiteRecordSearch::run($website);
        }

        if (Arr::has($changes, 'domain')) {
            $key = config('iris.cache.website.prefix')."_$website->domain";
            Cache::forget($key);
        }

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
                'nullable',
                'string',
                'regex:/^GTM-[A-Z0-9]+$/'
            ],
            'catalogue_template' => ['sometimes', 'array'],
            'luigisbox_tracker_id' => [
                'sometimes',
                'string',
                'nullable',
                'regex:/^\d{6}-\d{6}$/'
            ],
            'luigisbox_script_lbx' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'luigisbox_private_key' => ['sometimes', 'nullable', 'string'],
            'return_policy' => ['sometimes', 'string'],
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
            ],
            'script_website' => [
                'sometimes',
                'nullable',
                'string',
            ],
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
