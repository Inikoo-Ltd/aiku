<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\Web\SeoKeywordTag;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * Tracks a pasted or imported list of keywords with the same countries, language, check and tags.
 * Each line is sorted first: new, adds countries or devices (tracked in part already), already tracked
 * in every country, a repeat of an earlier line, or not a keyword. With `dry_run` that is all it does,
 * for the preview; otherwise the new ones are tracked, and the tags go on every valid keyword.
 */
class StoreSeoTrackedKeywords extends OrgAction
{
    use WithSeoEditAuthorisation;

    public const int MAX_LINES = 1000;

    public const string NEW = 'new';

    public const string ADD_COUNTRIES = 'add_countries';

    public const string TRACKED = 'tracked';

    public const string DUPLICATE = 'duplicate';

    public const string INVALID = 'invalid';

    public const string FAILED = 'failed';

    /**
     * @param  array{lines: array<int, string|null>, country_codes: array<int, string>, language_code: string, frequency: string, tag_ids?: array<int, int>, dry_run?: bool}  $modelData
     * @return array{rows: array<int, array{line: int, keyword: string, status: string, reason: string|null}>, summary: array<string, int>}
     */
    public function handle(Shop $shop, array $modelData): array
    {
        $rows = $this->classify($shop, $modelData['lines'], $modelData['country_codes']);

        if (!($modelData['dry_run'] ?? false)) {
            $rows = $this->store($shop, $rows, $modelData);
        }

        return [
            'rows'    => $rows,
            'summary' => array_merge(
                array_fill_keys([self::NEW, self::ADD_COUNTRIES, self::TRACKED, self::DUPLICATE, self::INVALID, self::FAILED], 0),
                array_count_values(array_column($rows, 'status'))
            ),
        ];
    }

    /**
     * @param  array<int, string|null>  $lines
     * @param  array<int, string>  $countryCodes
     * @return array<int, array{line: int, keyword: string, status: string, reason: string|null}>
     */
    public function classify(Shop $shop, array $lines, array $countryCodes): array
    {
        $keywords = [];

        foreach ($lines as $index => $line) {
            $keyword = StoreSeoTrackedKeyword::normalise((string) $line);

            if ($keyword !== '') {
                $keywords[] = ['line' => $index + 1, 'keyword' => $keyword];
            }
        }

        $tracked = $shop->seoTrackedKeywords()
            ->whereIn('keyword', array_column($keywords, 'keyword'))
            ->whereIn('country_code', $countryCodes)
            ->whereIn('device', SeoKeywordDeviceEnum::tracked())
            ->groupBy('keyword', 'country_code')
            ->select('keyword', 'country_code')
            ->selectRaw('COUNT(DISTINCT device) as devices')
            ->get()
            ->groupBy('keyword');

        $trackedCountries = $tracked->map(fn ($countries) => $countries->where('devices', '>=', count(SeoKeywordDeviceEnum::tracked()))->count());

        $seen = [];

        return array_map(function (array $row) use (&$seen, $tracked, $trackedCountries, $countryCodes) {
            [$status, $reason] = match (true) {
                mb_strlen($row['keyword']) > 255                       => [self::INVALID, __('Longer than 255 characters')],
                !preg_match('/[\p{L}\p{N}]/u', $row['keyword'])        => [self::INVALID, __('No letters or numbers')],
                isset($seen[$row['keyword']])                          => [self::DUPLICATE, __('Same as line :line', ['line' => $seen[$row['keyword']]])],
                $trackedCountries->get($row['keyword'], 0) >= count($countryCodes) => [self::TRACKED, __('Already tracked in every selected country')],
                $tracked->has($row['keyword'])                         => [self::ADD_COUNTRIES, __('Already tracked in part; the missing countries or devices are added')],
                default                                                => [self::NEW, null],
            };

            $seen[$row['keyword']] ??= $row['line'];

            return [...$row, 'status' => $status, 'reason' => $reason];
        }, $keywords);
    }

    /**
     * @param  array<int, array{line: int, keyword: string, status: string, reason: string|null}>  $rows
     * @return array<int, array{line: int, keyword: string, status: string, reason: string|null}>
     */
    private function store(Shop $shop, array $rows, array $modelData): array
    {
        $storeKeyword = StoreSeoTrackedKeyword::make();

        foreach ($rows as $index => $row) {
            if (!in_array($row['status'], [self::NEW, self::ADD_COUNTRIES], true)) {
                continue;
            }

            try {
                $storeKeyword->handle($shop, [
                    'keyword'       => $row['keyword'],
                    ...Arr::only($modelData, ['country_codes', 'language_code', 'frequency']),
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $rows[$index] = [...$row, 'status' => self::FAILED, 'reason' => __('Could not be saved, try again')];
            }
        }

        if ($modelData['tag_ids'] ?? []) {
            SetSeoKeywordTags::make()->handle($shop, [
                'keywords'    => array_column(array_filter($rows, fn (array $row) => in_array($row['status'], [self::NEW, self::ADD_COUNTRIES, self::TRACKED], true)), 'keyword'),
                'add_tag_ids' => $modelData['tag_ids'],
            ]);
        }

        return $rows;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('country_codes', array_values(array_unique(array_filter(array_map(fn ($code) => strtoupper(trim((string) $code)), Arr::wrap($this->get('country_codes')))))));

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
            'lines'           => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*'         => ['nullable', 'string', 'max:1000'],
            'country_codes'   => ['required', 'array', 'min:1', 'max:'.StoreSeoTrackedKeyword::MAX_COUNTRIES],
            'country_codes.*' => ['string', Rule::exists(Country::class, 'code')],
            'language_code'   => ['required', 'string', Rule::exists(Language::class, 'code')],
            'frequency'       => ['required', Rule::enum(SeoKeywordFrequencyEnum::class)],
            'tag_ids'         => ['sometimes', 'array'],
            'tag_ids.*'       => ['integer', Rule::exists(SeoKeywordTag::class, 'id')->where('shop_id', $this->shop->id)],
            'dry_run'         => ['sometimes', 'boolean'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'lines.required' => __('Paste at least one keyword, or choose a file.'),
            'lines.max'      => __('Import up to :max keywords at a time.', ['max' => self::MAX_LINES]),
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function jsonResponse(array $result): array
    {
        return $result;
    }
}
