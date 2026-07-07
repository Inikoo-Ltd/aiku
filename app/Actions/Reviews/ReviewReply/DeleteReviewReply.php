<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 18:49:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\ReviewReply;

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

        $translations = $review->translations;
        unset($translations['reply_message']);
        $review->update(['translations' => $translations]);

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
