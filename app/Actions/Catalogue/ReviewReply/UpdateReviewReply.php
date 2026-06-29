<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Actions\OrgAction;
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
