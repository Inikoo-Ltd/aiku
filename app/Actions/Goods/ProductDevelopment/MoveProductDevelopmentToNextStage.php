<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class MoveProductDevelopmentToNextStage extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        /** @var ProductDevelopment $productDevelopment */
        $productDevelopment = $request->route('productDevelopment');

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->canMoveProductDevelopmentStage($request->user(), $productDevelopment);
    }

    /**
     * @throws ValidationException
     */
    public function handle(ProductDevelopment $productDevelopment, User $user): ProductDevelopment
    {
        $gate = GetProductDevelopmentStageGate::run($productDevelopment);
        if (!$gate['can_move']) {
            throw ValidationException::withMessages(['stage' => $gate['reason']]);
        }

        return DB::transaction(function () use ($productDevelopment, $user) {
            $currentPosition = $productDevelopment->current_position;
            $nextPosition    = $currentPosition + 1;

            $productDevelopment->stages()
                ->where('position', $currentPosition)
                ->whereNotIn('state', [ProductDevelopmentStageStateEnum::COMPLETED->value, ProductDevelopmentStageStateEnum::SKIPPED->value])
                ->update([
                    'state'           => $this->isSkippedAt($productDevelopment, $currentPosition) ? ProductDevelopmentStageStateEnum::SKIPPED->value : ProductDevelopmentStageStateEnum::COMPLETED->value,
                    'completed_at'    => now(),
                    'completed_by_id' => $user->id,
                ]);

            $productDevelopment->stages()
                ->where('position', $nextPosition)
                ->where('state', ProductDevelopmentStageStateEnum::PENDING->value)
                ->update([
                    'state'      => ProductDevelopmentStageStateEnum::IN_PROGRESS->value,
                    'started_at' => now(),
                ]);

            $modelData = ['current_position' => $nextPosition];
            if ($nextPosition > ProductDevelopmentStageEnum::lastPosition()) {
                $modelData['status'] = ProductDevelopmentStatusEnum::COMPLETED;
            }

            $productDevelopment->updateQuietly($modelData);

            CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment, $user);

            RecordProductDevelopmentActivity::record(
                $productDevelopment,
                RecordProductDevelopmentActivity::STAGE_MOVED,
                ['stage' => ProductDevelopmentStageEnum::positionLabel($currentPosition, $productDevelopment->kind)],
                ['stage' => ProductDevelopmentStageEnum::positionLabel($nextPosition, $productDevelopment->kind)],
            );

            return $productDevelopment;
        });
    }

    /**
     * The artwork stage counts as skipped when the development has no design sections at all.
     */
    private function isSkippedAt(ProductDevelopment $productDevelopment, int $position): bool
    {
        return $position === ProductDevelopmentStageEnum::ARTWORK->position() && !$productDevelopment->designSections()->exists();
    }

    /**
     * @throws ValidationException
     */
    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopment
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($productDevelopment, $request->user());
    }

    /**
     * @throws ValidationException
     */
    public function action(ProductDevelopment $productDevelopment, User $user): ProductDevelopment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($productDevelopment->group, []);

        return $this->handle($productDevelopment, $user);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
