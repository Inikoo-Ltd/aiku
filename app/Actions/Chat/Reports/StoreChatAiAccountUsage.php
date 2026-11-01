<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Models\Chat\ChatAiAccountUsageSample;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Keeps a reading of a claude.ai account's weekly usage, posted by the local pace mod every few
 * minutes from every open session. A reading older than the last one is ignored; one that only
 * repeats it moves the last one's sampled_at, so the age shown is that of the latest reading.
 */
class StoreChatAiAccountUsage
{
    use AsAction;

    /**
     * @param array{account: string, percent_used: float|int|string, resets_at: string, sampled_at: string} $reading
     */
    public function handle(Group $group, array $reading): bool
    {
        $resetsAt  = Carbon::parse($reading['resets_at'])->utc();
        $sampledAt = Carbon::parse($reading['sampled_at'])->utc();

        $last = ChatAiAccountUsageSample::where('group_id', $group->id)
            ->where('account', $reading['account'])
            ->orderByDesc('sampled_at')
            ->first();

        if ($last && $sampledAt->lte($last->sampled_at)) {
            return false;
        }

        if ($last && (float) $last->percent_used === round((float) $reading['percent_used'], 2) && $last->resets_at->eq($resetsAt)) {
            $last->update(['sampled_at' => $sampledAt]);

            return false;
        }

        ChatAiAccountUsageSample::create([
            'group_id'     => $group->id,
            'account'      => $reading['account'],
            'percent_used' => $reading['percent_used'],
            'resets_at'    => $resetsAt,
            'sampled_at'   => $sampledAt,
        ]);

        return true;
    }

    public function authorize(ActionRequest $request): bool
    {
        return (bool) $request->user()?->can_use_mcp_customer_service;
    }

    public function rules(): array
    {
        return [
            'account'      => ['required', 'string', 'max:64'],
            'percent_used' => ['required', 'numeric', 'between:0,100'],
            'resets_at'    => ['required', 'date'],
            'sampled_at'   => ['required', 'date', 'before_or_equal:+5 minutes'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return ['stored' => $this->handle($request->user()->group, $request->validated())];
    }
}
