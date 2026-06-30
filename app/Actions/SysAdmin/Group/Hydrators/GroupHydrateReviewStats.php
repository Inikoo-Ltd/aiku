<?php

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\Reviews\Review;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateReviewStats implements ShouldBeUnique
{
    use AsAction;
    use BuildsReviewStats;

    public function getJobUniqueId(int|null $groupId): string
    {
        return (string) ($groupId ?? 'empty');
    }

    public function handle(int|null $groupId): void
    {
        if (!$groupId) {
            return;
        }

        $group = Group::query()->find($groupId);
        if (!$group) {
            return;
        }

        $stats = $this->buildReviewStats(
            Review::query()->where('group_id', $group->id)
        );

        $group->reviewStats()->updateOrCreate([], $stats);
    }
}
