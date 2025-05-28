<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Jan 2024 10:31:14 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebpages;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebpages;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageSeo;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateChildWebpages;
use App\Actions\Web\Webpage\Search\WebpageRecordSearch;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Rules\AlphaDashSlash;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebpage extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithImageSeo;

    private Webpage $webpage;


    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        // dd($webpage->id);
        $currentSeoData = Arr::get($modelData, 'seo_data');

        $oldSeoData = $webpage->seo_data;

        if ($currentSeoData) {
            $isUseCanonicalUrl = Arr::pull($currentSeoData, 'is_use_canonical_url');
            if ($isUseCanonicalUrl) {
                data_set($modelData, 'is_use_canonical_url', $isUseCanonicalUrl);
            }

            $canonicalUrl = Arr::pull($currentSeoData, 'canonical_url');
            if ($canonicalUrl) {
                data_set($modelData, 'canonical_url', $canonicalUrl);
            }

            $newData = [];
            data_set($newData, 'structured_data', Arr::pull($currentSeoData, 'structured_data', Arr::get($oldSeoData, 'structured_data')));
            data_set($newData, 'structured_data_type', Arr::pull($currentSeoData, 'structured_data_type', Arr::get($oldSeoData, 'structured_data_type')));
            data_set($newData, 'meta_title', Arr::pull($currentSeoData, 'meta_title', Arr::get($oldSeoData, 'meta_title')));
            data_set($newData, 'meta_description', Arr::pull($currentSeoData, 'meta_description', Arr::get($oldSeoData, 'meta_description')));

            data_set($modelData, 'seo_data', $newData);
        }

        $imageSeo = Arr::pull($modelData, 'seo_image');
        if ($imageSeo) {
            $webpage = $this->processSeo([
                'image' => $imageSeo
            ], $webpage);

            $source = $webpage->imageSources(1200, 1200, 'seoImage');

            if (!Arr::get($modelData, 'seo_data')) {
                data_set($oldSeoData, 'image', $source);
                data_set($modelData, 'seo_data', $oldSeoData);
            } else {
                data_set($modelData, 'seo_data.image', $source);
            }
        }


        $webpage = $this->update($webpage, $modelData, ['data', 'settings']);


        if ($webpage->wasChanged('state')) {
            GroupHydrateWebpages::dispatch($webpage->group)->delay($this->hydratorsDelay);
            OrganisationHydrateWebpages::dispatch($webpage->organisation)->delay($this->hydratorsDelay);
            WebsiteHydrateWebpages::dispatch($webpage->website)->delay($this->hydratorsDelay);
            if ($webpage->parent_id) {
                WebpageHydrateChildWebpages::dispatch($webpage->parent)->delay($this->hydratorsDelay);
            }
        }


        WebpageRecordSearch::run($webpage);

        return $webpage;
    }


    public function rules(): array
    {
        $rules = [
            'url'            => [
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
                            'value'  => $this->webpage->website->id
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->webpage->id
                        ],
                    ]
                ),
            ],
            'code'           => [
                'sometimes',
                'required',
                'ascii',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'webpages',
                    extraConditions: [

                        ['column' => 'website_id', 'value' => $this->webpage->website_id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->webpage->id
                        ],
                    ]
                ),

            ],
            'seo_image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'level'          => ['sometimes', 'integer'],
            'sub_type'       => ['sometimes', Rule::enum(WebpageSubTypeEnum::class)],
            'type'           => ['sometimes', Rule::enum(WebpageTypeEnum::class)],
            'state'          => ['sometimes', Rule::enum(WebpageStateEnum::class)],
            'google_search'  => ['sometimes', 'array'],
            'webpage_type'   => ['sometimes', 'array'],
            'ready_at'       => ['sometimes', 'date'],
            'live_at'        => ['sometimes', 'date'],
            'title'          => ['sometimes', 'string'],
            'description'    => ['sometimes', 'string'],
            'show_in_parent' => ['sometimes', 'nullable', 'boolean'],
        ];

        if (!$this->strict) {
            $rules                   = $this->noStrictUpdateRules($rules);
            $rules['migration_data'] = ['sometimes', 'array'];
        }

        return $rules;
    }

    public function action(Webpage $webpage, array $modelData, int $hydratorsDelay = 0, $strict = true, bool $audit = true): Webpage
    {
        if (!$audit) {
            Webpage::disableAuditing();
        }
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->webpage        = $webpage;

        $this->initialisation($webpage->organisation, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;

        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage, $this->validatedData);
    }


    public function inShop(Shop $shop, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($shop, $request);

        $modelData = [];
        foreach ($this->validatedData as $key => $value) {
            data_set(
                $modelData,
                match ($key) {
                    'google_search', 'webpage_type' => 'seo_data',
                    default => $key
                },
                $value
            );
        }

        return $this->handle($webpage, $modelData);
    }

    public function jsonResponse(Webpage $webpage): WebpageResource
    {
        return new WebpageResource($webpage);
    }
}
