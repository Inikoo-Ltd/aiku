<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:23:18 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\CRM\CustomersExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCustomers extends OrgAction
{
    use WithExportData;

    private const STREAM_THRESHOLD = 20000;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|Shop $parent, array $modelData): BinaryFileResponse|StreamedResponse
    {
        $type = $modelData['type'];
        $recipe = $modelData['filters'] ?? [];
        $states = $this->getStateFilter($modelData['state'] ?? []);
        $statuses = $this->getStatusFilter($modelData['status'] ?? []);

        $export = new CustomersExport($parent, $recipe, $states, $statuses);

        if ($type === ExportTypeEnum::XLSX->value && $export->query()->toBase()->count() < self::STREAM_THRESHOLD) {
            return $this->export($export, 'customers', $type);
        }

        $query = $export->query()->toBase()
            ->select($export->exportColumns())
            ->orderBy('customers.id');

        return $this->streamCsv($query, $export->headings(), 'customers');
    }

    public function rules(): array
    {
        return [
            'type'     => ['required', 'string', Rule::in('csv', 'xlsx')],
            'filters'  => ['sometimes', 'nullable', 'array'],
            'state'    => ['sometimes', 'nullable', 'array'],
            'state.*'  => ['string'],
            'status'   => ['sometimes', 'nullable', 'array'],
            'status.*' => ['string'],
        ];
    }

    protected function getStateFilter(array|string $states): array
    {
        if (!is_array($states)) {
            $states = [$states];
        }

        return array_values(array_intersect($states, array_keys(CustomerStateEnum::labels())));
    }

    protected function getStatusFilter(array|string $statuses): array
    {
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        return array_values(array_intersect($statuses, array_keys(CustomerStatusEnum::labels())));
    }


    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|StreamedResponse
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): BinaryFileResponse|StreamedResponse
    {
        $this->initialisation($organisation, $request);
        return $this->handle($shop, $this->validatedData);
    }
}
