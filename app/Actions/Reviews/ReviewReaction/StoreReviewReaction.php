<?php

namespace App\Actions\Reviews\ReviewReaction;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;
use Illuminate\Validation\Rules\Enum;

class StoreReviewReaction extends OrgAction
{
    public function action(Review $review, array $modelData): ReviewReaction
    {
        $this->initialisation($review->organisation, $modelData);

        return $this->handle($review, $this->validatedData);
    }

    public function handle(Review $review, array $modelData): ReviewReaction
    {
        $reviewReaction = $review->reactions()->create($modelData);

        return $reviewReaction;
    }

    public function rules(): array 
    {
        return [
            'customer_id'   => ['required', 'exists:customers,id'],
            'target'        => ['required', new Enum(ReviewReactionTargetEnum::class)],
            'type'          => ['required', new Enum(ReviewReactionTypeEnum::class)],
        ];
    }
}
