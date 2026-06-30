<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:23:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\OrgAction;
use App\Models\Reviews\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteReview extends OrgAction
{
    use AsAction;
    use HasReviewHydrators;

    /**
     * @throws \Throwable
     */
    public function handle(Review $review): bool
    {
        $isDeleted = DB::transaction(function () use ($review): bool {
            $isDeleted = $review->delete();

            return (bool)$isDeleted;
        });

        if ($isDeleted) {
            $this->reviewHydrators($review);
        }

        return $isDeleted;
    }

    /**
     * @throws \Throwable
     */
    public function asController(Review $review, ActionRequest $request): JsonResponse|RedirectResponse
    {
        $this->initialisationFromShop($review->shop, $request);

        $isDeleted = $this->handle($review);

        if (!$request->expectsJson()) {
            return redirect()->back();
        }

        return response()->json([
            'status'  => $isDeleted ? 'success' : 'failed',
            'message' => $isDeleted ? __('Review deleted successfully.') : __('Failed to delete review.'),
        ], $isDeleted ? 200 : 422);
    }


}
