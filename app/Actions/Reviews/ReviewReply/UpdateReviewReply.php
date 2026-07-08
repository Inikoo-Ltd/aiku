<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 18:49:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\ReviewReply;

use App\Actions\OrgAction;
use App\Actions\Reviews\TranslateReply;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateReviewReply extends OrgAction
{
    public function handle(Review $review, array $modelData): Review
    {
        $review->update([
            'reply_message' => $modelData['body'],
            'reply_at'      => now(),
        ]);
        TranslateReply::dispatch($review);
        return $review->refresh();
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    public function asController(Review $reviewReply, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromShop($reviewReply->shop, $request);

        $updatedReview = $this->handle($reviewReply, $this->validatedData);

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ]);
    }
}
