<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\OrgAction;
use App\Actions\Procurement\ProcurementNote\StoreProcurementNote;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Procurement turns a feedback into a standing instruction to the supplier: a pinned procurement note on the
 * supplier, so it shows on its next purchase orders and on the goods-in checklist. Nothing is carried forward
 * unless someone approves it here; retiring it is superseding the note.
 */
class CreateSupplierInstructionFromFeedback extends OrgAction
{
    private StockDeliveryFeedback $feedback;

    public function handle(StockDeliveryFeedback $feedback, User $user, array $modelData): StockDeliveryFeedback
    {
        if ($feedback->procurement_note_id) {
            return $feedback;
        }

        $supplier = $feedback->supplier;
        if (!$supplier) {
            throw ValidationException::withMessages(['body' => __('Only feedback about a supplier can become a supplier instruction')]);
        }

        $note = StoreProcurementNote::make()->action($supplier, [
            'title'           => Arr::get($modelData, 'title'),
            'body'            => $modelData['body'],
            'category'        => self::category($feedback->topics)->value,
            'visibility'      => ($supplier->agent_id ? ProcurementNoteVisibilityEnum::AGENT : ProcurementNoteVisibilityEnum::SUPPLIER)->value,
            'is_pinned'       => true,
            'organisation_id' => $feedback->organisation_id,
            'author_user_id'  => $user->id,
        ], strict: false);

        $feedback->update(['procurement_note_id' => $note->id]);

        return $feedback->refresh();
    }

    /**
     * @param array<int, string> $topics
     */
    public static function category(array $topics): ProcurementNoteCategoryEnum
    {
        return match (true) {
            (bool) array_intersect($topics, [StockDeliveryFeedbackTopicEnum::CARTON_LABELS->value, StockDeliveryFeedbackTopicEnum::PACKING->value]) => ProcurementNoteCategoryEnum::PACKAGING,
            in_array(StockDeliveryFeedbackTopicEnum::PRODUCT_QUALITY->value, $topics, true)                                                       => ProcurementNoteCategoryEnum::PRODUCT,
            in_array(StockDeliveryFeedbackTopicEnum::DOCUMENTS->value, $topics, true)                                                             => ProcurementNoteCategoryEnum::DELIVERY,
            default                                                                                                                                => ProcurementNoteCategoryEnum::GENERAL,
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body'  => ['required', 'string', 'max:10000'],
        ];
    }

    public function asController(StockDeliveryFeedback $stockDeliveryFeedback, ActionRequest $request): StockDeliveryFeedback
    {
        $this->feedback = $stockDeliveryFeedback;
        $this->initialisation($stockDeliveryFeedback->organisation, $request);

        return $this->handle($stockDeliveryFeedback, $request->user(), $this->validatedData);
    }

    public function action(StockDeliveryFeedback $stockDeliveryFeedback, User $user, array $modelData): StockDeliveryFeedback
    {
        $this->asAction = true;
        $this->feedback = $stockDeliveryFeedback;
        $this->initialisation($stockDeliveryFeedback->organisation, $modelData);

        return $this->handle($stockDeliveryFeedback, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
