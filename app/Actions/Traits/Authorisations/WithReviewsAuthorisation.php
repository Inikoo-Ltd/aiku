<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithReviewsAuthorisation
{
    use WithReviewsPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $this->canManageReviews($request->user(), $this->shop);

        return $this->canViewReviews($request->user(), $this->shop);
    }
}
