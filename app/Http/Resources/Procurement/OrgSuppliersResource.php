<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $org_supplier_slug
 * @property string $code
 * @property string $name
 * @property string $location
 * @property int $number_org_supplier_products
 * @property int $number_purchase_orders
 * @property int $number_stock_deliveries
 * @property int|null $number_open_purchase_orders
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property bool|null $status
 * @property int|null $org_agent_id
 * @property string|null $org_agent_slug
 * @property string|null $agent_code
 * @property string|null $agent_name
 * @property string|null $agreement_status
 * @property int|null $agreement_accepted_version
 */
class OrgSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'org_supplier_slug'            => $this->org_supplier_slug,
            'code'                         => $this->code,
            'name'                         => Supplier::nameFromContact($this->company_name ?? null, $this->contact_name ?? null) ?: $this->name,
            'name_source'                  => $this->nameSource(),
            'location'                     => json_decode($this->location),
            'number_org_supplier_products' => $this->number_org_supplier_products,
            'number_purchase_orders'       => $this->number_purchase_orders,
            'number_stock_deliveries'      => $this->number_stock_deliveries,
            'number_open_purchase_orders'  => (int) ($this->number_open_purchase_orders ?? 0),
            'open_incidents'               => (int) ($this->open_incidents ?? 0),
            'nickname'                     => $this->nickname(),
            'org_agent_slug'               => $this->org_agent_slug ?? null,
            'agent_code'                   => $this->agent_code ?? null,
            'agent_name'                   => $this->agent_name ?? null,
            'status_icon'                  => $this->statusIcon(),
            'agreement'                    => GetSupplierAgreementStatus::make()->badge($this->agreement_status ?? null, $this->agreement_accepted_version ?? null),
        ];
    }

    private function nickname(): ?string
    {
        return $this->name !== Supplier::nameFromContact($this->company_name ?? null, $this->contact_name ?? null) ? $this->name : null;
    }

    private function nameSource(): ?string
    {
        if (($this->company_name ?? '') != '') {
            return 'company';
        }

        return ($this->contact_name ?? '') != '' ? 'contact' : null;
    }

    /**
     * @return array{icon: string, class: string, tooltip: string}
     */
    private function statusIcon(): array
    {
        if ($this->status === false) {
            return ['icon' => 'fal fa-archive', 'class' => 'text-red-500', 'tooltip' => __('Archived')];
        }

        if ($this->org_agent_id ?? null) {
            return ['icon' => 'fal fa-people-arrows', 'class' => 'text-blue-500', 'tooltip' => __('Through Agent')];
        }

        return ['icon' => 'fal fa-person-dolly', 'class' => 'text-green-500', 'tooltip' => __('No agent')];
    }
}
