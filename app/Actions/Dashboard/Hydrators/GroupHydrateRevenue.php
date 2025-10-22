<?php

namespace App\Actions\Dashboard\Hydrators;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\GroupStats;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateRevenue implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:group-revenue {group}';

    public function asCommand(Command $command): void
    {
        $group = Group::where('slug', $command->argument('group'))->first();

        if (!$group) {
            $command->error("Group not found");
            return;
        }

        $this->handle($group->id);

        $command->info("Group successfully hydrated");
    }

    public function handle(int $groupId): void
    {
        $group = Group::find($groupId);

        if (!$group) {
            return;
        }

        $organisations = $group->organisations()->get();

        if ($organisations->isEmpty()) {
            return;
        }

        $stats = [
            'revenue_amount' => $organisations->sum(fn($o) => $o->orderingStats->revenue_amount ?? 0),
            'lost_revenue_other_amount' => $organisations->sum(fn($o) => $o->orderingStats->lost_revenue_other_amount ?? 0),
            'lost_revenue_out_of_stock_amount' => $organisations->sum(fn($o) => $o->orderingStats->lost_revenue_out_of_stock_amount ?? 0),
            'lost_revenue_replacements_amount' => $organisations->sum(fn($o) => $o->orderingStats->lost_revenue_replacements_amount ?? 0),
            'lost_revenue_compensations_amount' => $organisations->sum(fn($o) => $o->orderingStats->lost_revenue_compensations_amount ?? 0),
        ];

        $groupStats = $group->orderingStats ?? new GroupStats(['group_id' => $group->id]);
        $groupStats->fill($stats);
        $groupStats->save();
    }
}
