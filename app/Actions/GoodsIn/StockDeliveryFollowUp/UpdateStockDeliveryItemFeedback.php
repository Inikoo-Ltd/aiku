<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Its author corrects a line note.
 */
class UpdateStockDeliveryItemFeedback extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDeliveryItemFeedback $note;

    public function handle(StockDeliveryItemFeedback $note, array $modelData): StockDeliveryItemFeedback
    {
        $note->update(Arr::only($modelData, ['issue', 'comment']));

        return $note;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || ($request->user()->id === $this->note->author_id && self::userCanBookIn($request->user(), $this->organisation, 'incoming.%d.edit'));
    }

    public function rules(): array
    {
        return StoreStockDeliveryItemFeedback::noteRules();
    }

    public function asController(StockDeliveryItemFeedback $stockDeliveryItemFeedback, ActionRequest $request): StockDeliveryItemFeedback
    {
        $this->note = $stockDeliveryItemFeedback;
        $this->initialisation($stockDeliveryItemFeedback->organisation, $request);

        return $this->handle($stockDeliveryItemFeedback, $this->validatedData);
    }

    public function action(StockDeliveryItemFeedback $stockDeliveryItemFeedback, array $modelData): StockDeliveryItemFeedback
    {
        $this->asAction = true;
        $this->note     = $stockDeliveryItemFeedback;
        $this->initialisation($stockDeliveryItemFeedback->organisation, $modelData);

        return $this->handle($stockDeliveryItemFeedback, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
