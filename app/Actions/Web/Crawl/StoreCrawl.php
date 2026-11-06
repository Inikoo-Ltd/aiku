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
