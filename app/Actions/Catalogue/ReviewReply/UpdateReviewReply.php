<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReviewReply
{
    use AsAction;

    public function handle(Review $review, string $message): Review
    {
        $review->update([
            'reply_message' => $message,
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
        $validated = $request->validated();

        $updatedReview = $this->handle($reviewReply, $validated['body']);

        return response()->json([
            'status' => 'success',
            'data'   => $updatedReview,
        ]);
    }
}
