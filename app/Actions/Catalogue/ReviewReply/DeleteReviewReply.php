<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Actions\OrgAction;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteReviewReply extends OrgAction
{
    public function handle(Review $review): Review
    {
        $review->update([
            'replied'       => false,
            'reply_message' => null,
            'reply_at'      => null,
            'reply_by'      => null,
        ]);

        return $review->refresh();
    }

    public function asController(Review $reviewReply, ActionRequest $request): JsonResponse|RedirectResponse
    {
        $this->initialisationFromShop($reviewReply->shop, $request);

        $this->handle($reviewReply);

        if (!$request->expectsJson()) {
            return redirect()->back();
        }

        return response()->json([
            'status'  => 'success',
            'message' => __('Review reply deleted successfully.'),
        ]);
    }
}
