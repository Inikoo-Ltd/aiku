<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Enums\Web\Crawl\CrawlScheduleFrequencyEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\CrawlSchedule;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets when a website is crawled on its own, besides the weekly audit of every website. Scheduled
 * crawls are full crawls, so they keep the health score and the page scores up to date.
 */
class SaveCrawlSchedule extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Website $website, array $modelData, ?User $user = null): CrawlSchedule
    {
        $frequency = CrawlScheduleFrequencyEnum::from($modelData['frequency']);

        /** @var CrawlSchedule $schedule */
        $schedule = CrawlSchedule::firstOrNew(['website_id' => $website->id]);

        $schedule->fill([
            'frequency'    => $frequency,
            'weekday'      => $frequency === CrawlScheduleFrequencyEnum::WEEKLY ? (int) $modelData['weekday'] : null,
            'day_of_month' => $frequency === CrawlScheduleFrequencyEnum::MONTHLY ? (int) $modelData['day_of_month'] : null,
            'hour'         => (int) $modelData['hour'],
            'is_active'    => (bool) Arr::get($modelData, 'is_active', true),
            'user_id'      => $user?->id ?? $schedule->user_id,
        ]);

        $schedule->next_run_at = $schedule->is_active ? $schedule->nextRunAfter(now()) : null;
        $schedule->save();

        return $schedule;
    }

    public function rules(): array
    {
        return [
            'frequency'    => ['required', Rule::enum(CrawlScheduleFrequencyEnum::class)],
            'weekday'      => ['required_if:frequency,'.CrawlScheduleFrequencyEnum::WEEKLY->value, 'nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['required_if:frequency,'.CrawlScheduleFrequencyEnum::MONTHLY->value, 'nullable', 'integer', 'between:1,31'],
            'hour'         => ['required', 'integer', 'between:0,23'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Website $website, ActionRequest $request): CrawlSchedule
    {
        abort_unless($website->shop, 404);

        $this->initialisationFromShop($website->shop, $request);

        if ($website->state !== WebsiteStateEnum::LIVE) {
            throw ValidationException::withMessages([
                'website' => __(':domain is not live, so it cannot be crawled.', ['domain' => $website->domain]),
            ]);
        }

        return $this->handle($website, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
