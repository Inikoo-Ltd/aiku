<?php

namespace App\Actions\Reviews\Traits;

use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;

trait WithHydrateReviewReactionStats
{
    public function hydrateReactions(Review $review)
    {
        $reviewReactions = $this->getReactionStat(
                field: 'type',
                enum: ReviewReactionTypeEnum::class,
                models: ReviewReaction::class,
                where: function ($q) use ($review) {
                    $q->where('review_id', $review->id)
                        ->where('target', ReviewReactionTargetEnum::REVIEW);
                }
            );
        
        $replyReactions = $this->getReactionStat(
                field: 'type',
                enum: ReviewReactionTypeEnum::class,
                models: ReviewReaction::class,
                customColumnPrefix: 'replay_',
                where: function ($q) use ($review) {
                    $q->where('review_id', $review->id)
                        ->where('target', ReviewReactionTargetEnum::REVIEW_REPLY);
                }
            );

        $review->update(array_merge($reviewReactions, $replyReactions));
    }

    private function getReactionStat(
        string $field,
        $enum,
        $models,
        $where = false,
        $customColumnPrefix = null,
        $connection = 'aiku'
    ): array {
        $stats = [];

        $applyWhere = false;
        if ($this->isClosure($where)) {
            $applyWhere = true;
        } else {
            $where = function () {};
        }

        $count = $models::on($connection)
            ->selectRaw("$field, count(*) as total")
            ->when(
                $applyWhere, $where
            )
            ->groupBy($field)
            ->pluck('total', $field)->all();
            
        foreach ($enum::cases() as $case) {
            $accessor = $case->snake().'s';
            if ($customColumnPrefix) $accessor = $customColumnPrefix.$accessor;
            data_set($stats, $accessor, data_get($count, $case->value, 0));
        }

        return $stats;
    }

    public function isClosure($t): bool
    {
        return $t instanceof \Closure;
    }
}
