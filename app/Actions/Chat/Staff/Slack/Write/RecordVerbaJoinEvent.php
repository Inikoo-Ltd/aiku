<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Who reached the Verba join gate and what they did, one verba_join_usage row per day, user and event.
 * The screen sends shown, policy_opened and later; the server records agreed, connected, slack_declined, failed, asked_link and not_in_slack.
 */
class RecordVerbaJoinEvent
{
    use AsAction;

    public const array SCREEN_EVENTS = ['shown', 'policy_opened', 'later'];

    public function handle(int $userId, string $event): void
    {
        DB::statement(
            'INSERT INTO verba_join_usage (date, user_id, event, count) VALUES (CURRENT_DATE, ?, ?, 1)
             ON CONFLICT (date, user_id, event) DO UPDATE SET count = verba_join_usage.count + 1',
            [$userId, $event]
        );
    }

    public function rules(): array
    {
        return ['event' => ['required', 'string', Rule::in(self::SCREEN_EVENTS)]];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->handle($request->user()->id, $request->validated('event'));

        return response()->noContent();
    }
}
