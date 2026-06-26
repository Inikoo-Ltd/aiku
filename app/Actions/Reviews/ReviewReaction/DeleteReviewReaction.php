<?php

namespace App\Actions\Reviews\ReviewReaction;

use App\Actions\OrgAction;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;

class DeleteReviewReaction extends OrgAction
{
    public function action(ReviewReaction $reviewReaction): Review
    {
        $this->initialisation($reviewReaction->review->organisation, []);

        return $this->handle($reviewReaction);
    }

    public function handle(ReviewReaction $reviewReaction): Review
    {
        $review = $reviewReaction->review;
        $reviewReaction->delete();

        return $review;
    }
}
