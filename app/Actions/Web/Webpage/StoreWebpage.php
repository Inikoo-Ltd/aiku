<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 08:32:10 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Helpers\Snapshot\StoreWebpageSnapshot;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebpages;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateChildWebpages;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\Webpage\WebpageSeoStructureTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\Website;
use App\Models\Web\Webpage;
use App\Rules\AlphaDashSlash;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebpage extends OrgAction
{
    use AsAction;
    use WithNoStrictRules;
    use WithStoreWebpage;

    private Website $website;

    private Webpage|Website $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Website|Webpage $parent, array $modelData): Webpage
    {
        if (!Arr::exists($modelData, 'type')) {
            $modelData['type'] = WebpageTypeEnum::CONTENT;
        }

        if (!Arr::exists($modelData, 'sub_type')) {
            $modelData['sub_type'] = WebpageSubTypeEnum::CONTENT;
        }

        if (Arr::exists($modelData, 'seo_structure_type')) {
            $modelData['data'] = [
                'seo_structure_type' => Arr::pull($modelData, 'seo_structure_type')
            ];
        }

        data_set($modelData, 'url', '', overwrite: false);
        data_set($modelData, 'shop_id', $parent->shop_id);

        if ($parent instanceof Webpage) {
            data_set($modelData, 'website_id', $parent->website_id);
            data_set($modelData, 'level', $parent->level + 1);
        } else {
            data_set($modelData, 'level', 1);
        }


        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);

        $webpage = DB::transaction(function () use ($parent, $modelData) {
            /** @var Webpage $webpage */
            $webpage = $parent->webpages()->create($modelData);
            $webpage->stats()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $webpage->timeSeries()->create(['frequency' => $frequency]);
            }
            $webpage->refresh();

            $snapshot = StoreWebpageSnapshot::run(
                $webpage,
                [
                    'layout'   => [
                        'web_blocks' => []
                    ],
                    'checksum' => hash('sha256', '')
                ],
            );

            $webpage->update(
                [
                    'unpublished_snapshot_id' => $snapshot->id,
                ]
            );

            $model = $webpage->model;
            if ($model instanceof Product) {
                UpdateProduct::make()->action($model, [
                    'webpage_id' => $webpage->id,
                    'url'        => $webpage->url,
                ]);
            } elseif ($model instanceof ProductCategory) {
                UpdateProductCategory::make()->action($model, [
                    'webpage_id' => $webpage->id,
                    'url'        => $webpage->url,
                ]);
            } elseif ($model instanceof Collection) {
                UpdateCollection::make()->action($model, [
                    'webpage_id' => $webpage->id,
                    'url'        => $webpage->url,
                ]);
            }


            if ($this->strict) {
                if ($model instanceof Product) {
                    $this->createWebBlock($webpage, 'product-1');
                    $this->createWebBlock($webpage, 'luigi-trends-1');
                    $this->createWebBlock($webpage, 'luigi-last-seen-1');
                    $this->createWebBlock($webpage, 'luigi-item-alternatives-1');
                } elseif ($model instanceof Collection) {
                    $this->createWebBlock($webpage, 'families-1');
                    $this->createWebBlock($webpage, 'products-1');
                } elseif ($model instanceof ProductCategory) {
                    if ($model->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                        $this->createWebBlock($webpage, 'families-1');
                        $this->createWebBlock($webpage, 'products-1');
                    } elseif ($model->type == ProductCategoryTypeEnum::DEPARTMENT) {
                        $this->createWebBlock($webpage, 'sub-departments-1');
                        $this->createWebBlock($webpage, 'products-1');
                        $this->createWebBlock($webpage, 'families-1');
                    } elseif ($model->type == ProductCategoryTypeEnum::FAMILY) {
                        $this->createWebBlock($webpage, 'family-1');
                        $this->createWebBlock($webpage, 'products-1');
                        $this->createWebBlock($webpage, 'luigi-trends-1');
                        $this->createWebBlock($webpage, 'luigi-last-seen-1');
                    }
                }

                if ($webpage->type == WebpageTypeEnum::BLOG) {
                    $this->createWebBlock($webpage, 'blog', $webpage);
                }
            }


            UpdateWebpageCanonicalUrl::run($webpage);

            return $webpage;
        });

        WebpageRecordSearch::dispatch($webpage);
        GroupHydrateWebpages::dispatch($webpage->group)->delay($this->hydratorsDelay);
        OrganisationHydrateWebpages::dispatch($webpage->organisation)->delay($this->hydratorsDelay);
        WebsiteHydrateWebpages::dispatch($webpage->website)->delay($this->hydratorsDelay);
        if ($webpage->parent_id) {
            WebpageHydrateChildWebpages::dispatch($webpage->parent)->delay($this->hydratorsDelay);
        }

        return $webpage;
    }

    public function htmlResponse(Webpage $webpage): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
    {
        return match ($webpage->website->type) {
            WebsiteTypeEnum::FULFILMENT => Inertia::location(route('grp.org.fulfilments.show.web.webpages.show', [
                'organisation' => $this->fulfilment->organisation->slug,
                'fulfilment'   => $this->fulfilment->slug,
                'website'      => $webpage->website->slug,
                'webpage'      => $webpage->slug
            ])),
            default => match ($webpage->type) {
                WebpageTypeEnum::BLOG => Inertia::location(route('grp.org.shops.show.web.blogs.show', [
                    'organisation' => $this->shop->organisation->slug,
                    'shop'         => $this->shop->slug,
                    'website'      => $webpage->website->slug,
                    'webpage'      => $webpage->slug
                ])),
                default => Inertia::location(route('grp.org.shops.show.web.webpages.show', [
                    'organisation' => $this->shop->organisation->slug,
                    'shop'         => $this->shop->slug,
                    'website'      => $webpage->website->slug,
                    'webpage'      => $webpage->slug
                ]))
            }
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (property_exists($this, 'fulfilment') && isset($this->fulfilment)) {
            return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        }

        return $request->user()->authTo("web.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'url'                => [
                'sometimes',
                'required',
                'ascii',
                'lowercase',
                'max:255',
                new AlphaDashSlash(),
                new IUnique(
                    table: 'webpages',
                    extraConditions: [
                        [
                            'column' => 'website_id',
                            'value'  => $this->website->id
                        ],

                        ['column' => 'deleted_at', 'operator' => 'null'],

                    ]
                ),
            ],
            'code'               => [
                'required',
                'ascii',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'webpages',
                    extraConditions: [
                        ['column' => 'website_id', 'value' => $this->website->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'sub_type'           => ['sometimes', Rule::enum(WebpageSubTypeEnum::class)],
            'type'               => ['sometimes', Rule::enum(WebpageTypeEnum::class)],
            'state'              => ['sometimes', Rule::enum(WebpageStateEnum::class)],
            'is_fixed'           => ['sometimes', 'boolean'],
            'ready_at'           => ['sometimes', 'date'],
            'live_at'            => ['sometimes', 'date'],
            'model_type'         => ['sometimes', 'string'],
            'model_id'           => ['sometimes', 'integer'],
            'title'              => ['required', 'string'],
            'seo_structure_type' => ['sometimes', 'nullable', Rule::enum(WebpageSeoStructureTypeEnum::class)],


        ];

        if ($this->parent instanceof Webpage) {
            $rules['url'] = [
                'required',
                'ascii',
                'lowercase',
                'max:255',
                new AlphaDashSlash(),
                new IUnique(
                    table: 'webpages',
                    extraConditions: [
                        [
                            'column' => 'website_id',
                            'value'  => $this->website->id
                        ],
                    ]
                ),
            ];
        }

        if (!$this->strict) {
            $rules                   = $this->noStrictStoreRules($rules);
            $rules['migration_data'] = ['sometimes', 'array'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function inFulfilment(Fulfilment $fulfilment, Website $website, ActionRequest $request): Webpage
    {
        $this->parent  = $website;
        $this->website = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Shop $shop, Website $website, ActionRequest $request): Webpage
    {
        $this->parent  = $website;
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inBlog(Shop $shop, Website $website, ActionRequest $request): Webpage
    {
        $this->parent  = $website;
        $this->website = $website;
        $this->set('type', WebpageTypeEnum::BLOG);
        $this->set('sub_type', WebpageSubTypeEnum::BLOG);
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $this->validatedData);
    }


    /**
     * @throws \Throwable
     */
    public function action(Website|Webpage $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Webpage
    {
        if (!$audit) {
            Webpage::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->parent         = $parent;
        $this->website        = $parent instanceof Website ? $parent : $parent->website;
        $this->initialisationFromShop($this->website->shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }


}
