<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Actions\OrgAction;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class StoreReviewReply extends OrgAction
{
    public function handle(Review $review, array $modelData): Review
    {
        $review->update([
            'replied'       => true,
            'reply_message' => $modelData['body'],
            'reply_at'      => now(),
            'reply_by'      => data_get($modelData, 'reply_by'),
        ]);

        return $review->refresh();
    }

    public function rules(): array
    {
        return [
            'reviewable_id' => ['required', 'integer', 'exists:reviews,id'],
            'body'          => ['required', 'string', 'max:10000'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $review = Review::findOrFail($request->integer('reviewable_id'));

        $this->initialisationFromShop($review->shop, $request);

        $updatedReview = $this->handle($review, array_merge($this->validatedData, [
            'reply_by' => $request->user()?->id,
        ]));

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ], 201);
    }
}
