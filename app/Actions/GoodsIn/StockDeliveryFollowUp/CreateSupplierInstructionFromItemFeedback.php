<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Procurement turns a line note into a standing instruction to that line's supplier, as it does with a feedback
 * about a supplier: a pinned procurement note, made once.
 */
class CreateSupplierInstructionFromItemFeedback extends OrgAction
{
    public function handle(StockDeliveryItemFeedback $note, User $user, array $modelData): StockDeliveryItemFeedback
    {
        return DB::transaction(function () use ($note, $user, $modelData) {
            $note = StockDeliveryItemFeedback::lockForUpdate()->findOrFail($note->id);
            if ($note->procurement_note_id) {
                return $note;
            }

            $supplier = $note->supplier;
            if (!$supplier) {
                throw ValidationException::withMessages(['body' => __('Only a note on a supplier\'s line can become a supplier instruction')]);
            }

            $instruction = CreateSupplierInstructionFromFeedback::pinnedNote($supplier, $note->organisation_id, $user, $modelData, CreateSupplierInstructionFromFeedback::category([$note->issue->topic()->value]));
            $note->update(['procurement_note_id' => $instruction->id]);

            return $note->refresh();
        });
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

    public function asController(StockDeliveryItemFeedback $stockDeliveryItemFeedback, ActionRequest $request): StockDeliveryItemFeedback
    {
        $this->initialisation($stockDeliveryItemFeedback->organisation, $request);

        return $this->handle($stockDeliveryItemFeedback, $request->user(), $this->validatedData);
    }

    public function action(StockDeliveryItemFeedback $stockDeliveryItemFeedback, User $user, array $modelData): StockDeliveryItemFeedback
    {
        $this->asAction = true;
        $this->initialisation($stockDeliveryItemFeedback->organisation, $modelData);

        return $this->handle($stockDeliveryItemFeedback, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
