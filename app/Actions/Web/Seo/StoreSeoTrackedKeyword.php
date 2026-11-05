<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use Closure;
use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Web\Webpage;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\DataForSeo\DataForSeoLocations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreSeoTrackedKeyword extends OrgAction
{
    use WithSeoEditAuthorisation;

    public const int MAX_COUNTRIES = 30;

    /**
     * @var array<string, string>
     */
    private array $languages = [];

    /**
     * One row per country on every tracked device, in the language each country has data for. A keyword already
     * tracked in a country is left as it is, so adding it again with more countries only adds the
     * new ones.
     *
     * @param  array{keyword: string, country_codes: array<int, string>, language_code: string, frequency: string, target_webpage_id?: int|null}  $modelData
     * @return Collection<int, SeoTrackedKeyword>
     */
    public function handle(Shop $shop, array $modelData): Collection
    {
        $trackedKeywords = new Collection();

        foreach ($modelData['country_codes'] as $countryCode) {
            foreach (SeoKeywordDeviceEnum::tracked() as $device) {
                $trackedKeywords->push($shop->seoTrackedKeywords()->firstOrCreate([
                    'keyword'       => $modelData['keyword'],
                    'country_code'  => $countryCode,
                    'language_code' => $this->languageFor($countryCode, $modelData['language_code']),
                    'device'        => $device,
                ], Arr::only($modelData, ['frequency', 'target_webpage_id'])));
            }
        }

        return $trackedKeywords;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('keyword')) {
            $this->set('keyword', self::normalise((string) $this->get('keyword')));
        }

        $countryCodes = $this->has('country_codes') ? Arr::wrap($this->get('country_codes')) : Arr::wrap($this->get('country_code'));

        $this->set('country_codes', array_values(array_unique(array_filter(array_map(fn ($code) => strtoupper(trim((string) $code)), $countryCodes)))));

        if ($this->has('language_code')) {
            $this->set('language_code', strtolower((string) $this->get('language_code')));
        }

        if (!$this->has('frequency')) {
            $this->set('frequency', SeoKeywordFrequencyEnum::ON_REQUEST->value);
        }
    }

    public function rules(): array
    {
        return [
            'keyword'           => [
                'required',
                'string',
                'max:255',
                fn (string $attribute, mixed $value, Closure $fail) => $this->isTrackedEverywhere((string) $value)
                    ? $fail(__('This keyword is already tracked in every selected country.'))
                    : null,
            ],
            'country_codes'     => ['required', 'array', 'min:1', 'max:'.self::MAX_COUNTRIES],
            'country_codes.*'   => ['string', Rule::exists(Country::class, 'code')],
            'language_code'     => ['required', 'string', Rule::exists(Language::class, 'code')],
            'frequency'         => ['required', Rule::enum(SeoKeywordFrequencyEnum::class)],
            'target_webpage_id' => ['sometimes', 'nullable', Rule::exists(Webpage::class, 'id')->where('shop_id', $this->shop->id)],
        ];
    }

    private function isTrackedEverywhere(string $keyword): bool
    {
        if ($this->get('country_codes') === []) {
            return false;
        }

        $untracked = collect($this->get('country_codes'))->contains(fn (string $countryCode) => SeoTrackedKeyword::query()
            ->where('shop_id', $this->shop->id)
            ->where('keyword', $keyword)
            ->where('country_code', $countryCode)
            ->where('language_code', $this->languageFor($countryCode, (string) $this->get('language_code')))
            ->whereIn('device', SeoKeywordDeviceEnum::tracked())
            ->count() < count(SeoKeywordDeviceEnum::tracked()));

        return !$untracked;
    }

    /**
     * The language a country is checked in: the one asked for when DataForSEO has data for it in that
     * country, else the country's own language, so a keyword tracked in Bulgaria, Greece and Denmark
     * is checked in Bulgarian, Greek and Danish. Without DataForSEO the language asked for is kept.
     */
    public function languageFor(string $countryCode, string $languageCode): string
    {
        return $this->languages[$countryCode.'|'.$languageCode] ??= $this->resolveLanguage($countryCode, $languageCode);
    }

    public static function normalise(string $keyword): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $keyword)));
    }

    private function resolveLanguage(string $countryCode, string $languageCode): string
    {
        $client       = DataForSeoClient::make();

        if (!$client) {
            return $languageCode;
        }

        try {
            return GetKeywordIdeas::make()->location($client, $countryCode, $languageCode)[1];
        } catch (ValidationException) {
            try {
                return DataForSeoLocations::forCountry($client, $countryCode)['languages'][0] ?? $languageCode;
            } catch (DataForSeoException) {
                return $languageCode;
            }
        } catch (DataForSeoException) {
            return $languageCode;
        }
    }

    public function asController(Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
