<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use App\Services\SiteAudit\CustomExtraction;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Starts a crawl of one of our websites from the site crawler, with the settings chosen in its
 * dialog. One crawl of a website runs at a time.
 */
class StoreCrawl extends OrgAction
{
    use WithSeoEditAuthorisation;

    private const int MAX_PAGES_LIMIT = 200000;

    private const int MAX_DEPTH_LIMIT = 50;

    public function handle(Website $website, array $modelData, ?User $user = null): Crawl
    {
        $runningCrawl = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('state', '!=', CrawlStateEnum::FINISH)
            ->latest('id')
            ->first();

        if ($runningCrawl && !AuditWebsite::closeIfAbandoned($runningCrawl)) {
            throw ValidationException::withMessages([
                'website' => __('A crawl of :domain is already running. Stop it before starting another one.', ['domain' => $website->domain]),
            ]);
        }

        $isListMode = Arr::get($modelData, 'mode') === AuditWebsite::MODE_LIST;
        $listedUrls = $isListMode ? array_values(array_unique(array_filter(
            array_map('trim', Arr::get($modelData, 'urls', [])),
            fn (string $url) => AuditWebsite::isWebsiteUrl($website, $url)
        ))) : [];

        if ($isListMode && $listedUrls === []) {
            throw ValidationException::withMessages([
                'urls' => __('None of the URLs belongs to :domain.', ['domain' => $website->domain]),
            ]);
        }

        $crawl = AuditWebsite::startFor(
            website: $website,
            trigger: CrawlTriggerEnum::USER,
            maxPages: Arr::get($modelData, 'max_pages'),
            concurrency: (int) Arr::get($modelData, 'concurrency', AuditWebsite::DEFAULT_CONCURRENCY),
            config: [
                'max_depth'            => Arr::get($modelData, 'max_depth'),
                'include'              => array_values(array_filter(Arr::get($modelData, 'include', []))),
                'exclude'              => array_values(array_filter(Arr::get($modelData, 'exclude', []))),
                'respect_robots_txt'   => (bool) Arr::get($modelData, 'respect_robots_txt', true),
                'crawl_subdomains'     => (bool) Arr::get($modelData, 'crawl_subdomains', false),
                'check_external_links' => (bool) Arr::get($modelData, 'check_external_links', true),
                'check_images'         => (bool) Arr::get($modelData, 'check_images', true),
                'mode'                 => $isListMode ? AuditWebsite::MODE_LIST : AuditWebsite::MODE_SPIDER,
                'urls'                 => $listedUrls,
                'custom_search'        => array_values(array_map(fn (array $search) => [
                    'name'  => $search['name'],
                    'mode'  => $search['mode'],
                    'type'  => $search['type'],
                    'value' => $search['value'],
                ], Arr::get($modelData, 'custom_search', []))),
                'extractions'          => array_values(array_map(fn (array $extraction) => [
                    'name'      => $extraction['name'],
                    'type'      => $extraction['type'],
                    'selector'  => $extraction['selector'],
                    'output'    => $extraction['output'] ?? 'text',
                    'attribute' => $extraction['attribute'] ?? null,
                ], Arr::get($modelData, 'extractions', []))),
            ],
            name: Arr::get($modelData, 'name'),
            user: $user
        );

        AuditWebsite::dispatch($crawl);

        return $crawl;
    }

    public function rules(): array
    {
        $validPattern = function (string $attribute, mixed $value, Closure $fail) {
            if (!is_string($value) || !AuditWebsite::isValidPattern($value)) {
                $fail(__('":pattern" is not a valid regular expression.', ['pattern' => is_string($value) ? $value : '']));
            }
        };

        $validSearch = function (string $attribute, mixed $value, Closure $fail) {
            $type = request()->input(str_replace('.value', '.type', $attribute));

            if (!is_string($value) || !CustomExtraction::isValidSearch((string) $type, $value)) {
                $fail(__('":value" is not a valid regular expression.', ['value' => is_string($value) ? $value : '']));
            }
        };

        $validSelector = function (string $attribute, mixed $value, Closure $fail) {
            $type = request()->input(str_replace('.selector', '.type', $attribute));

            if (!is_string($value) || !CustomExtraction::isValidSelector((string) $type, $value)) {
                $fail(__('":selector" is not a valid :type.', ['selector' => is_string($value) ? $value : '', 'type' => strtoupper((string) $type)]));
            }
        };

        return [
            'name'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'max_pages'            => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGES_LIMIT],
            'max_depth'            => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::MAX_DEPTH_LIMIT],
            'concurrency'          => ['sometimes', 'integer', 'min:1', 'max:'.AuditWebsite::CONCURRENT_FETCH_BUDGET],
            'include'              => ['sometimes', 'array', 'max:20'],
            'include.*'            => ['nullable', 'string', 'max:255', $validPattern],
            'exclude'              => ['sometimes', 'array', 'max:20'],
            'exclude.*'            => ['nullable', 'string', 'max:255', $validPattern],
            'respect_robots_txt'   => ['sometimes', 'boolean'],
            'crawl_subdomains'     => ['sometimes', 'boolean'],
            'check_external_links' => ['sometimes', 'boolean'],
            'check_images'         => ['sometimes', 'boolean'],
            'mode'                 => ['sometimes', 'in:'.AuditWebsite::MODE_SPIDER.','.AuditWebsite::MODE_LIST],
            'urls'                 => ['required_if:mode,'.AuditWebsite::MODE_LIST, 'array', 'max:'.AuditWebsite::LIST_MAX_URLS],
            'urls.*'               => ['nullable', 'string', 'max:2048'],
            'custom_search'            => ['sometimes', 'array', 'max:'.AuditWebsite::CUSTOM_ITEMS_MAX],
            'custom_search.*.name'     => ['required', 'string', 'max:60'],
            'custom_search.*.mode'     => ['required', 'in:'.implode(',', CustomExtraction::SEARCH_MODES)],
            'custom_search.*.type'     => ['required', 'in:'.implode(',', CustomExtraction::SEARCH_TYPES)],
            'custom_search.*.value'    => ['required', 'string', 'max:255', $validSearch],
            'extractions'              => ['sometimes', 'array', 'max:'.AuditWebsite::CUSTOM_ITEMS_MAX],
            'extractions.*.name'       => ['required', 'string', 'max:60'],
            'extractions.*.type'       => ['required', 'in:'.implode(',', CustomExtraction::EXTRACTION_TYPES)],
            'extractions.*.selector'   => ['required', 'string', 'max:500', $validSelector],
            'extractions.*.output'     => ['sometimes', 'in:'.implode(',', CustomExtraction::EXTRACTION_OUTPUTS)],
            'extractions.*.attribute'  => ['required_if:extractions.*.output,attribute', 'nullable', 'string', 'max:100'],
        ];
    }

    public function getValidationAttributes(): array
    {
        return [
            'max_pages'               => __('max URLs'),
            'max_depth'               => __('max clicks deep'),
            'concurrency'             => __('URLs at once'),
            'include.*'               => __('pattern'),
            'exclude.*'               => __('pattern'),
            'urls'                    => __('URLs'),
            'custom_search.*.name'    => __('name'),
            'custom_search.*.mode'    => __('match'),
            'custom_search.*.type'    => __('search type'),
            'custom_search.*.value'   => __('text to search for'),
            'extractions.*.name'      => __('name'),
            'extractions.*.type'      => __('extraction type'),
            'extractions.*.selector'  => __('selector'),
            'extractions.*.output'    => __('value to keep'),
            'extractions.*.attribute' => __('attribute name'),
        ];
    }

    public function asController(Website $website, ActionRequest $request): Crawl
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, $this->validatedData, $request->user());
    }

    public function htmlResponse(Crawl $crawl): RedirectResponse
    {
        return redirect()->route('grp.websites.crawler.show', [$crawl->id]);
    }
}
