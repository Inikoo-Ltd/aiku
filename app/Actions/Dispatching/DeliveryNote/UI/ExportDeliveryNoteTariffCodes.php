<?php

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\Dispatching\DeliveryNoteTariffCodesExport;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDeliveryNoteTariffCodes extends OrgAction
{
    use WithExportData;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote, array $modelData): BinaryFileResponse|StreamedResponse
    {
        $type   = $modelData['type'];
        $fields = $modelData['columns'] ?? [];

        $export = new DeliveryNoteTariffCodesExport($deliveryNote, $fields);

        if ($type === ExportTypeEnum::XLSX->value) {
            return $this->export($export, 'tariff-codes', $type);
        }

        return $this->streamCsv($export->dataQuery(), $export->headings(), 'tariff-codes');
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', 'string', Rule::in('csv', 'xlsx')],
            'columns'   => ['sometimes', 'nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(DeliveryNoteTariffCodesExport::fieldDefinitions()))],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "orders.{$this->shop->id}.view",
            "orders.{$this->shop->id}.edit",
            "dispatching.{$this->warehouse->id}.view",
            "fulfilment.{$this->warehouse->id}.view",
        ]);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): BinaryFileResponse|StreamedResponse
    {
        $this->warehouse = $deliveryNote->warehouse;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $this->validatedData);
    }
}
