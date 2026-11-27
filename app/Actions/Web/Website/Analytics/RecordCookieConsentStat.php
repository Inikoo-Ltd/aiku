<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 27 Nov 2026 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\Analytics;

use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Counts what our own cookie banner does, per website and day, for the accept / reject / ignore
 * rates marketing reports every month (INI-572). Only counters are kept, nothing about the visitor,
 * so it needs no consent itself. "shown" is sent once per browser session while the visitor has no
 * choice saved; "choice" only for their first choice, not for later changes from Cookie settings.
 */
class RecordCookieConsentStat
{
    use AsAction;

    public const array CATEGORIES = ['analytics', 'marketing', 'preferences'];

    public function handle(Website $website, array $modelData): void
    {
        if (!Arr::get($website->settings, 'own_cookie_banner')) {
            return;
        }

        $counts = array_fill_keys(['shown', 'accepted_all', 'rejected_all', 'partial', ...self::CATEGORIES], 0);

        if ($modelData['event'] === 'shown') {
            $counts['shown'] = 1;
        } else {
            $granted = array_values(array_filter(self::CATEGORIES, fn (string $category) => (bool) Arr::get($modelData, $category)));

            $counts[match (count($granted)) {
                0                       => 'rejected_all',
                count(self::CATEGORIES) => 'accepted_all',
                default                 => 'partial',
            }] = 1;

            foreach ($granted as $category) {
                $counts[$category] = 1;
            }
        }

        $columns = array_keys($counts);

        DB::statement(
            'INSERT INTO website_cookie_consent_stats AS stats (website_id, date, '.implode(', ', $columns).', created_at, updated_at)
             VALUES (?, ?, '.implode(', ', array_fill(0, count($columns), '?')).', now(), now())
             ON CONFLICT (website_id, date) DO UPDATE SET '
            .implode(', ', array_map(fn (string $column) => "$column = stats.$column + excluded.$column", $columns))
            .', updated_at = now()',
            [$website->id, now()->toDateString(), ...array_values($counts)]
        );
    }

    public function rules(): array
    {
        return [
            'event'       => ['required', 'in:shown,choice'],
            'analytics'   => ['sometimes', 'boolean'],
            'marketing'   => ['sometimes', 'boolean'],
            'preferences' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->handle($request->website, $request->validated());
    }
}
