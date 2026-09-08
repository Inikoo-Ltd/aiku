<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 28 Jul 2026 09:26:03 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithExportData;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\Ordering\CustomerSalesChannelOrdersExport;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportRetinaCustomerSalesChannelOrders extends RetinaAction
{
    use WithExportData;

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, string $type = ExportTypeEnum::XLSX->value): BinaryFileResponse
    {
        $export = new CustomerSalesChannelOrdersExport($customerSalesChannel);

        return $this->export($export, 'orders-'.$customerSalesChannel->slug, $type);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');

        return $customerSalesChannel->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in([ExportTypeEnum::XLSX->value, ExportTypeEnum::CSV->value])]
        ];
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request->input('type', ExportTypeEnum::XLSX->value));
    }
}
