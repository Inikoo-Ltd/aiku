<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Models\SysAdmin\User;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How long the Customer Inbox takes in the staff browsers (INI-145), sent in batches by
 * useInboxTimings: opening the inbox, opening a conversation, a reply being accepted, and every
 * time the page froze. The server side is in NightOwl; this is what the person in front of it waits.
 */
class StoreChatInboxTimings
{
    use AsAction;

    public const array MEASURES = ['inbox_open', 'inbox_page', 'conversation_open', 'conversation_fetch', 'reply_sent', 'freeze'];

    /**
     * @param  array<int, array{measure: string, duration_ms: int, channel?: string|null, is_full_load?: bool|null}>  $samples
     */
    public function handle(?User $user, array $samples): int
    {
        $rows = array_map(fn (array $sample) => [
            'user_id'      => $user?->id,
            'measure'      => $sample['measure'],
            'channel'      => Arr::get($sample, 'channel'),
            'is_full_load' => in_array($sample['measure'], ['inbox_open', 'inbox_page']) ? Arr::get($sample, 'is_full_load') : null,
            'duration_ms'  => (int) $sample['duration_ms'],
            'created_at'   => now(),
        ], $samples);

        DB::table('chat_inbox_timings')->insert($rows);

        return count($rows);
    }

    public function rules(): array
    {
        return [
            'samples'                => ['required', 'array', 'min:1', 'max:50'],
            'samples.*.measure'      => ['required', 'string', 'in:'.implode(',', self::MEASURES)],
            'samples.*.duration_ms'  => ['required', 'integer', 'between:0,300000'],
            'samples.*.channel'      => ['sometimes', 'nullable', 'string', 'in:website,email,whatsapp'],
            'samples.*.is_full_load' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->handle($request->user(), $request->validated('samples'));

        return response()->noContent();
    }
}
