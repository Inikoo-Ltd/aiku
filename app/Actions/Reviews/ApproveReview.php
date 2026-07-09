<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 18:35:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Actions\OrgAction;
use App\Actions\Reviews\Traits\HasReviewHydrators;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ApproveReview extends OrgAction
{
    use HasReviewHydrators;

    public function handle(Review $review, ?int $approvedBy = null): Review
    {
        $review->update([
            'review_status' => ReviewStatusEnum::APPROVED,
            'state'         => ReviewStateEnum::PUBLISHED,
            'approved'      => true,
            'approved_by'   => $approvedBy,
            'published_at'  => $review->published_at ?? now(),
        ]);

        $review->refresh();

        $this->reviewHydrators($review);

        return $review;
    }

    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisationFromShop($review->shop, $request);

        return $this->handle($review, $request->user()->id);
    }

    public function htmlResponse(Review $review, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Review approved and published.'),
        ]);
    }
}
