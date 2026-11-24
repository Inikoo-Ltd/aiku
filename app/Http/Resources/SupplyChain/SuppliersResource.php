<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string $location
 * @property bool $status
 * @property int|null $agent_id
 * @property string|null $agent_slug
 * @property string|null $agent_code
 * @property string|null $agent_name
 * @property int|null $relationship_contact_user_id
 * @property string|null $relationship_contact_name
 * @property int $number_supplier_products
 * @property int $number_purchase_orders
 * @property int|null $number_open_purchase_orders
 * @property int $number_stock_deliveries
 * @property string|null $agreement_status
 * @property int|null $agreement_accepted_version
 */
class SuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->resource->officialName(),
            'name_source'              => $this->nameSource(),
            'nickname'                 => $this->resource->nickname(),
            'agent_slug'               => $this->agent_slug ?? null,
            'agent_code'               => $this->agent_code ?? null,
            'agent_name'               => $this->agent_name ?? null,
            'relationship_contact_user_id' => $this->relationship_contact_user_id ?? null,
            'relationship_contact_name'    => $this->relationship_contact_name ?? null,
            'location'                 => $this->location,
            'status_icon'              => $this->getStatusIcon(),
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders'   => $this->number_purchase_orders,
            'number_open_purchase_orders' => (int) ($this->number_open_purchase_orders ?? 0),
            'number_stock_deliveries'  => $this->number_stock_deliveries,
            'open_incidents'           => (int) ($this->open_incidents ?? 0),
            'agreement'                => $this->agreement(),
        ];
    }

    protected function nameSource(): ?string
    {
        if ($this->company_name != '') {
            return 'company';
        }

        return $this->contact_name != '' ? 'contact' : null;
    }

    /**
     * @return array{status: string, label: string, colour: string, icon: string}|null
     */
    protected function agreement(): ?array
    {
        return GetSupplierAgreementStatus::make()->badge($this->agreement_status ?? null, $this->agreement_accepted_version ?? null);
    }

    protected function getStatusIcon(): array
    {
        if (!$this->status) {
            return [
                'icon'    => 'fal fa-archive',
                'class'   => 'text-red-500',
                'tooltip' => __('Archived'),
            ];
        }

        if ($this->agent_id) {
            return [
                'icon'    => 'fal fa-people-arrows',
                'class'   => 'text-blue-500',
                'tooltip' => __('Through Agent'),
            ];
        }

        return [
            'icon'    => 'fal fa-person-dolly',
            'class'   => 'text-green-500',
            'tooltip' => __('No agent'),
        ];
    }
}
