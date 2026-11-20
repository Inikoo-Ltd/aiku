<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Compliance gives the green light on the version of an artwork in review. Only then can the owner or an
 * approver approve it. The OK belongs to that version: the next version needs a new one.
 */
class GiveProductDevelopmentArtworkComplianceOk extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentArtwork $artwork;

    private User $compliance;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->artwork->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canGiveComplianceOk($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentArtwork $artwork, User $user): ProductDevelopmentArtwork
    {
        $artwork->update([
            'compliance_ok_by_id' => $user->id,
            'compliance_ok_at'    => now(),
        ]);

        RecordProductDevelopmentActivity::record($artwork->productDevelopment, RecordProductDevelopmentActivity::ARTWORK_COMPLIANCE_OK, [], [
            'artwork' => ProductDevelopmentArtworks::label($artwork),
            'version' => $artwork->version,
        ]);

        ProductDevelopmentArtworks::notifyReviewers($artwork, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($artwork->productDevelopment->refresh(), $user);

        return $artwork;
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentArtworks::canChange($this->artwork)) {
            $validator->errors()->add('compliance_ok', __('The artwork is signed off and locked'));

            return;
        }

        if ($this->artwork->status !== ProductDevelopmentArtworkStatusEnum::IN_REVIEW) {
            $validator->errors()->add('compliance_ok', __('This artwork is not in review'));

            return;
        }

        if ($this->artwork->compliance_ok_at !== null) {
            $validator->errors()->add('compliance_ok', __('This version already has the Compliance OK'));

            return;
        }

        if ($reason = ProductDevelopmentArtworks::complianceOkBlockedReason($this->artwork, $this->compliance)) {
            $validator->errors()->add('compliance_ok', $reason);
        }
    }

    public function asController(ProductDevelopmentArtwork $productDevelopmentArtwork, ActionRequest $request): ProductDevelopmentArtwork
    {
        $this->artwork    = $productDevelopmentArtwork;
        $this->compliance = $request->user();
        $this->initialisationFromGroup($productDevelopmentArtwork->productDevelopment->group, $request);

        return $this->handle($productDevelopmentArtwork, $request->user());
    }

    public function action(ProductDevelopmentArtwork $artwork, User $user): ProductDevelopmentArtwork
    {
        $this->asAction   = true;
        $this->artwork    = $artwork;
        $this->compliance = $user;
        $this->initialisationFromGroup($artwork->productDevelopment->group, []);

        return $this->handle($artwork, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
