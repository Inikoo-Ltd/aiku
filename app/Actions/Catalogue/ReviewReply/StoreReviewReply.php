<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Models\Reviews\Review;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReviewReply
{
    use AsAction;

    public function handle(Review $review, string $message, ?User $user = null): Review
    {
        $review->update([
            'replied'       => true,
            'reply_message' => $message,
            'reply_at'      => now(),
            'reply_by'      => $user?->id,
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
        $validated = $request->validated();

        $review = Review::findOrFail($validated['reviewable_id']);

        $updatedReview = $this->handle($review, $validated['body'], $request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ], 201);
    }
}
