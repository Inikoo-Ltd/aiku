<?php

namespace App\Actions\Catalogue\Review;

use App\Actions\Catalogue\Review\Traits\HasReviewHydrators;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RejectReview extends OrgAction
{
    use HasReviewHydrators;

    public function handle(Review $review): Review
    {
        $review->update([
            'review_status' => ReviewStatusEnum::REJECTED,
            'state'         => ReviewStateEnum::REJECTED,
            'approved'      => false,
            'published_at'  => null,
        ]);

        $review->refresh();

        $this->reviewHydrators($review);

        return $review;
    }

    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisationFromShop($review->shop, $request);

        return $this->handle($review);
    }

    public function htmlResponse(Review $review, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Review rejected.'),
        ]);
    }
}
