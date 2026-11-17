<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 20:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SupplyChain\SupplierSubmission;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A material planning note nobody has replied to (still received or under review, with no reply to the supplier)
 * the set number of days after the supplier sent it goes to the people chosen in the supplier page settings, once
 * per sending: data.escalated_for keeps the sending it was escalated for, so a note sent again can escalate again.
 * Nobody chosen means no escalation.
 */
class EscalateOverdueSupplierPlanningNotes
{
    use AsAction;

    public const int DEFAULT_DAYS = 5;

    public string $commandSignature = 'supplier-portal:escalate_planning_notes';

    /**
     * @return array{days: int, user_ids: array<int, int>}
     */
    public static function settings(Group $group): array
    {
        $settings = data_get($group->settings, 'supplier_portal.planning_escalation', []);

        return [
            'days'     => (int) data_get($settings, 'days', self::DEFAULT_DAYS),
            'user_ids' => array_values(array_map('intval', (array) data_get($settings, 'user_ids', []))),
        ];
    }

    public function handle(Group $group): int
    {
        $settings = self::settings($group);
        $users    = $this->recipients($group, $settings['user_ids']);
        if ($users->isEmpty()) {
            return 0;
        }

        $escalated = 0;
        SupplierSubmission::where('group_id', $group->id)
            ->where('type', SupplierSubmissionTypeEnum::MATERIAL_PLANNING)
            ->whereIn('state', [SupplierSubmissionStateEnum::RECEIVED, SupplierSubmissionStateEnum::UNDER_REVIEW])
            ->whereNull('feedback')
            ->where('submitted_at', '<=', now()->subDays($settings['days']))
            ->with(['supplier', 'supplierProduct'])
            ->chunkById(100, function (Collection $submissions) use ($users, &$escalated) {
                foreach ($submissions as $submission) {
                    $sending = $submission->submitted_at->toIso8601String();
                    if (data_get($submission->data, 'escalated_for') === $sending) {
                        continue;
                    }
                    NotifyPurchasingOfSupplierPlanningNote::make()->send($submission, $users, UserNotificationEnum::SUPPLIER_PLANNING_OVERDUE);
                    $submission->update(['data' => [...$submission->data, 'escalated_for' => $sending]]);
                    $escalated++;
                }
            });

        return $escalated;
    }

    /**
     * @param  array<int, int>  $userIds
     *
     * @return Collection<int, User>
     */
    private function recipients(Group $group, array $userIds): Collection
    {
        if (!$userIds) {
            return collect();
        }

        return User::where('group_id', $group->id)->where('status', true)->whereIn('id', $userIds)->get();
    }

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info($group->code.': '.$this->handle($group).' planning notes escalated');
        }

        return 0;
    }
}
