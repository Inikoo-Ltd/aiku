<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithReviewsEditAuthorisation
{
    use WithReviewsPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canManageReviews($request->user(), $this->shop);
    }
}
