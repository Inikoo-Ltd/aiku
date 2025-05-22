<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 11:30:40 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebsites;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebsites;
use App\Actions\Web\Website\Search\WebsiteRecordSearch;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\Website;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreWebsite extends OrgAction
{
    private Fulfilment|Shop $parent;

    public function handle(Shop $shop, array $modelData): Website
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set(
            $modelData,
            'type',
            match ($shop->type) {
                ShopTypeEnum::FULFILMENT => WebsiteTypeEnum::FULFILMENT,
                ShopTypeEnum::DROPSHIPPING => WebsiteTypeEnum::DROPSHIPPING,
                ShopTypeEnum::B2B => WebsiteTypeEnum::B2B,
                ShopTypeEnum::B2C => WebsiteTypeEnum::B2C,
            }
        );
        /** @var Website $website */
        $website = $shop->website()->create($modelData);
        $website->webStats()->create();
        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $website->timeSeries()->create(['frequency' => $frequency]);
        }
        $website->refresh();

        SeedWebsiteOutboxes::run($website);

        $headerSnapshot = StoreWebsiteSnapshot::run(
            $website,
            [
                'scope'  => SnapshotScopeEnum::HEADER,
                'layout' => []
            ]
        );
        $menuSnapshot = StoreWebsiteSnapshot::run(
            $website,
            [
                'scope'  => SnapshotScopeEnum::MENU,
                'layout' => []
            ]
        );
        $footerSnapshot = StoreWebsiteSnapshot::run(
            $website,
            [
                'scope'  => SnapshotScopeEnum::FOOTER,
                'layout' => []
            ],
        );
        $website->update(
            [
                'unpublished_header_snapshot_id' => $headerSnapshot->id,
                'unpublished_menu_snapshot_id' => $menuSnapshot->id,
                'unpublished_footer_snapshot_id' => $footerSnapshot->id,
                'published_layout'               => [
                    'header' => $headerSnapshot->layout,
                    'menu' => $menuSnapshot->layout,
                    'footer' => $footerSnapshot->layout
                ]
            ]
        );
        $website->webStats()->create();
        //AddWebsiteToCloudflare::run($website);

        GroupHydrateWebsites::dispatch($shop->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWebsites::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        WebsiteRecordSearch::dispatch($website);

        if ($website->state != WebsiteStateEnum::CLOSED) {
            $website = SeedWebsiteFixedWebpages::run($website);
        }

        return $website;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->parent instanceof Fulfilment) {
            return $request->user()->authTo("fulfilment-shop.{$this->parent->id}.edit");
        } elseif ($this->parent instanceof Shop) {
            return $request->user()->authTo("web.{$this->parent->id}.edit");
        }

        return false;
    }

    public function rules(): array
    {
        $rules = [
            'domain' => [
                'required',
                'string',
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
                    ]
                ),
            ],
            'code'   => [
                'required',
                'ascii',
                'lowercase',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'websites',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),
            ],
            'name'   => ['required', 'string', 'max:255'],
            'state'  => ['sometimes', Rule::enum(WebsiteStateEnum::class)],
            'status' => ['sometimes', 'boolean'],
        ];

        if (!$this->strict) {
            $rules['fetched_at']  = ['sometimes', 'date'];
            $rules['source_id']   = ['sometimes', 'string'];
            $rules['created_at']  = ['sometimes', 'nullable', 'date'];
            $rules['launched_at'] = ['sometimes', 'nullable', 'date'];
            $rules['domain']      = [
                'required',
                'string',
                'ascii',
                'lowercase',
                'max:255',
                new IUnique(
                    table: 'websites',
                    extraConditions: [
                        [
                            'column' => 'organisation_id',
                            'value'  => $this->organisation->id
                        ]
                    ]
                ),
            ];
        }

        return $rules;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('domain')) {
            $this->set('domain', preg_replace('/^https?\/\//', '', $this->get('domain')));
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->shop->website) {
            $validator->errors()->add('domain', 'This shop already have a website');
        }
    }

    public function htmlResponse(Website $website): RedirectResponse
    {
        if ($this->parent instanceof Fulfilment) {
            return Redirect::route('grp.org.fulfilments.show.web.websites.show', [
                $this->organisation->slug,
                $this->parent->slug,
                $website->slug
            ]);
        }

        return Redirect::route('grp.org.shops.show.web.websites.show', [
            $this->organisation->slug,
            $this->parent->slug,
            $website->slug
        ]);
    }

    public function asController(Shop $shop, ActionRequest $request): Website
    {
        $this->parent = $shop;
        $this->shop   = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function inFulfilment(Fulfilment $fulfilment, ActionRequest $request): Website
    {
        $this->parent = $fulfilment;
        $this->shop   = $fulfilment->shop;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment->shop, $this->validatedData);
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Website
    {
        if (!$audit) {
            Website::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->shop           = $shop;
        $this->initialisation($shop->organisation, $modelData);

        return $this->handle($shop, $this->validatedData);
    }


}
