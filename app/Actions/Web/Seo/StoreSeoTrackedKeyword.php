<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSeoTrackedKeyword extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Shop $shop, array $modelData): SeoTrackedKeyword
    {
        /** @var SeoTrackedKeyword $trackedKeyword */
        $trackedKeyword = $shop->seoTrackedKeywords()->create($modelData);

        return $trackedKeyword;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('keyword')) {
            $this->set('keyword', Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $this->get('keyword')))));
        }

        if ($this->has('country_code')) {
            $this->set('country_code', strtoupper((string) $this->get('country_code')));
        }

        if ($this->has('language_code')) {
            $this->set('language_code', strtolower((string) $this->get('language_code')));
        }
    }

    public function rules(): array
    {
        return [
            'keyword'           => [
                'required',
                'string',
                'max:255',
                Rule::unique(SeoTrackedKeyword::class, 'keyword')->where(fn ($query) => $query
                    ->where('shop_id', $this->shop->id)
                    ->where('country_code', $this->get('country_code'))
                    ->where('language_code', $this->get('language_code'))
                    ->where('device', $this->get('device'))),
            ],
            'country_code'      => ['required', 'string', Rule::exists(Country::class, 'code')],
            'language_code'     => ['required', 'string', Rule::exists(Language::class, 'code')],
            'device'            => ['required', Rule::enum(SeoKeywordDeviceEnum::class)],
            'frequency'         => ['required', Rule::enum(SeoKeywordFrequencyEnum::class)],
            'target_webpage_id' => ['sometimes', 'nullable', Rule::exists(Webpage::class, 'id')->where('shop_id', $this->shop->id)],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'keyword.unique' => __('This keyword is already tracked for that country, language and device.'),
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): SeoTrackedKeyword
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
