<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Files the invoice behind a row of the cost checklist. The agent or supplier invoice, and the bills behind shipping
 * and duty, are kept on the delivery and sent to the AI to be read, unless that document was read already: the
 * reading waits under the invoice costing or the service invoices for a person to apply it (HELP-4329). The invoice
 * of an extra is kept on its row. It never changes an amount.
 */
class AttachStockDeliveryCostInvoice extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;

    private StockDelivery $stockDelivery;

    public function rules(): array
    {
        return [
            'type'          => ['required', Rule::enum(StockDeliveryCostTypeEnum::class)],
            'cost'          => ['sometimes', 'nullable', 'integer'],
            'attachments'   => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => ['file', 'max:50000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->get('cost') && !$this->stockDelivery->costs()->where('id', $this->get('cost'))->where('type', $this->get('type'))->exists()) {
            $validator->errors()->add('cost', __('This cost is not in this stock delivery'));
        }

        if ($this->get('type') === StockDeliveryCostTypeEnum::EXTRA->value && !$this->get('cost')) {
            $validator->errors()->add('cost', __('Add the extra expense before uploading its invoice'));
        }
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $type           = StockDeliveryCostTypeEnum::from($modelData['type']);
        $isReadByAI     = $type !== StockDeliveryCostTypeEnum::EXTRA;
        $isGoodsInvoice = $type === StockDeliveryCostTypeEnum::AGENT_INVOICE;
        $model          = $isReadByAI ? $stockDelivery : $stockDelivery->costs()->findOrFail($modelData['cost']);

        foreach ($modelData['attachments'] as $file) {
            $media = SaveModelAttachment::make()->action($model, [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'scope'        => $isReadByAI && !$isGoodsInvoice ? PurchaseOrderAttachmentScopeEnum::SERVICE_INVOICE->value : PurchaseOrderAttachmentScopeEnum::INVOICE->value,
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);

            if ($isReadByAI && !in_array(data_get(ReadStockDeliveryInvoice::reading($stockDelivery, $media), 'state'), ['reading', 'read'], true)) {
                $isGoodsInvoice ? ReadStockDeliveryInvoice::queue($stockDelivery, $media) : ReadStockDeliveryServiceInvoice::queue($stockDelivery, $media);
            }
        }

        return $stockDelivery;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
