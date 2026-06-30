<?php

namespace App\Actions\Reviews\ReviewReaction;

use App\Actions\OrgAction;
use App\Actions\Reviews\Traits\WithHydrateReviewReactionStats;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;

class DeleteReviewReaction extends OrgAction
{
    use WithHydrateReviewReactionStats;

    public function action(ReviewReaction $reviewReaction): Review
    {
        $this->initialisation($reviewReaction->review->organisation, []);

        return $this->handle($reviewReaction);
    }

    public function handle(ReviewReaction $reviewReaction): Review
    {
        $review = $reviewReaction->review;
        $reviewReaction->delete();

        $this->hydrateReactions($review);

        return $review;
    }
}
