<?php

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Catalogue\Review\Hydrators\Concerns\BuildsReviewStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
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

        $baseQuery = DB::query()->fromSub(
            DB::table('shop_reviews')
                ->select(['status', 'rating_main', 'rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e'])
                ->where('group_id', $group->id)
                ->whereNull('deleted_at')
                ->unionAll(
                    DB::table('product_reviews')
                        ->select(['status', 'rating_main', 'rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e'])
                        ->where('group_id', $group->id)
                        ->whereNull('deleted_at')
                )
                ->unionAll(
                    DB::table('product_category_reviews')
                        ->select(['status', 'rating_main', 'rating_a', 'rating_b', 'rating_c', 'rating_d', 'rating_e'])
                        ->where('group_id', $group->id)
                        ->whereNull('deleted_at')
                ),
            'reviews'
        );

        $stats = $this->buildReviewStats($baseQuery);

        $group->reviewStats()->updateOrCreate([], $stats);
    }
}
