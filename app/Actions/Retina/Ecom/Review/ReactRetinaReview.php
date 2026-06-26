<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\RetinaAction;
use App\Actions\Reviews\ReactReview;
use App\Actions\Reviews\ReviewReaction\DeleteReviewReaction;
use App\Actions\Reviews\ReviewReaction\StoreReviewReaction;
use App\Actions\Reviews\ReviewReaction\UpdateReviewReaction;
use App\Enums\Catalogue\Review\ReviewReactionTargetEnum;
use App\Enums\Catalogue\Review\ReviewReactionTypeEnum;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Lorisleiva\Actions\ActionRequest;

class ReactRetinaReview extends RetinaAction
{
    public function handle(Review $review, array $modelData): Review
    {
        $reaction = ReviewReaction::where('review_id', $review->id)
            ->where('customer_id', $this->customer->id)
            ->where('target', $modelData['target'])
            ->first();

        $type = Arr::has($modelData, 'type');

        if ($reaction) {
            if ($reaction->type->value != $type) {
                UpdateReviewReaction::make()->action($reaction, Arr::only($modelData, 'type'));
            } else {
                DeleteReviewReaction::make()->action($reaction);
            }
        } else {
            StoreReviewReaction::make()->action($review, [
                'customer_id'   => $this->customer->id,
                ...$modelData
            ]);
        }
        $review->refresh();

        return $review;
    }

    public function rules(): array
    {
        return [
            'target'    => ['required', new Enum(ReviewReactionTargetEnum::class)],
            'type'      => ['sometimes', new Enum(ReviewReactionTypeEnum::class)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return (bool) $this->customer;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisation($request);

        return $this->handle($review, $this->validatedData);
    }

    public function jsonResponse(Review $review): JsonResponse
    {
        return response()->json([
            'likes'           => $review->likes,
            'dislikes'        => $review->dislikes,
            'replay_likes'    => $review->replay_likes,
            'replay_dislikes' => $review->replay_dislikes,
        ]);
    }
}
