<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * Its author takes back a note put on the wrong line, with its photos, while the round is not sent. A draft round
 * left empty stays and is simply not shown.
 */
class DeleteStockDeliveryItemFeedback extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDeliveryItemFeedback $note;

    public function handle(StockDeliveryItemFeedback $note): void
    {
        DB::table('model_has_attachments')
            ->where('model_type', 'StockDelivery')
            ->where('model_id', $note->stock_delivery_id)
            ->where('scope', PurchaseOrderAttachmentScopeEnum::FEEDBACK->value)
            ->where('sub_scope', StoreStockDeliveryItemFeedback::photoSubScope($note->id))
            ->delete();

        $note->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || ($request->user()->id === $this->note->author_id
            && !$this->note->procurement_note_id
            && !$this->note->feedback->isSent()
            && self::userCanBookIn($request->user(), $this->organisation, 'incoming.%d.edit'));
    }

    public function asController(StockDeliveryItemFeedback $stockDeliveryItemFeedback, ActionRequest $request): void
    {
        $this->note = $stockDeliveryItemFeedback;
        $this->initialisation($stockDeliveryItemFeedback->organisation, $request);

        $this->handle($stockDeliveryItemFeedback);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
