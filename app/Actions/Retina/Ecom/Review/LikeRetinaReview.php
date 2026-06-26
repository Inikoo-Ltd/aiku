<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\RetinaAction;
use App\Actions\Reviews\LikeReview;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class LikeRetinaReview extends RetinaAction
{
    public function handle(Review $review, array $modelData): Review
    {
        return LikeReview::make()->action($review, $modelData['target'], (bool) $modelData['is_like'], $this->customer->id);
    }

    public function rules(): array
    {
        return [
            'target'  => ['required', Rule::in(['review', 'reply'])],
            'is_like' => ['required', 'boolean'],
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
