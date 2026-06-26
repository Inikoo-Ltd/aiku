<?php

namespace App\Actions\Reviews\ReviewReaction;

use App\Actions\OrgAction;
use App\Actions\Reviews\Traits\WithHydrateReviewReactionStats;
use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;
use Illuminate\Validation\Rules\Enum;

class UpdateReviewReaction extends OrgAction
{
    use WithHydrateReviewReactionStats;

    public function action(ReviewReaction $reviewReaction, array $modelData): ReviewReaction
    {
        $this->initialisation($reviewReaction->review->organisation, $modelData);

        return $this->handle($reviewReaction, $this->validatedData);
    }

    public function handle(ReviewReaction $reviewReaction, array $modelData): ReviewReaction
    {
        $reviewReaction->update($modelData);

        $this->hydrateReactions($reviewReaction->review);
        
        return $reviewReaction;
    }

    public function rules(): array 
    {
        return [
            'type'          => ['required', new Enum(ReviewReactionTypeEnum::class)],
        ];
    }
}
