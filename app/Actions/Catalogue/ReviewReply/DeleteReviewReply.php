<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Models\Reviews\ReviewReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReviewReply
{
    use AsAction;

    public function handle(ReviewReply $reviewReply): bool
    {
        return (bool) $reviewReply->delete();
    }

    public function asController(ReviewReply $reviewReply, ActionRequest $request): JsonResponse|RedirectResponse
    {
        $isDeleted = $this->handle($reviewReply);

        if (!$request->expectsJson()) {
            return redirect()->back();
        }

        return response()->json([
            'status' => $isDeleted ? 'success' : 'failed',
            'message' => $isDeleted ? __('Review reply deleted successfully.') : __('Failed to delete review reply.'),
        ], $isDeleted ? 200 : 422);
    }
}
