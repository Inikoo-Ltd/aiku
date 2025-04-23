<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Mar 2025 21:39:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dashboards;

use App\Actions\Traits\Dashboards\WithDashboardIntervalValues;
use App\Actions\Utils\Abbreviate;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * @property mixed $slug
 * @property mixed $state
 * @property mixed $category_currency_id
 * @property mixed $name
 * @property mixed $organisation_currency_id
 */
class DashboardInvoiceCategoriesInOrganisationSalesResource extends JsonResource
{
    use WithDashboardIntervalValues;


    protected string $categoryCurrencyCode;
    protected string $organisationCurrencyCode;

    public function toArray($request): array
    {


        $currencyCategoryId = $this->category_currency_id;
        $categoryCurrencyCode = Cache::remember('currency_code_'.$currencyCategoryId, 3600 * 24 * 30, function () use ($currencyCategoryId) {
            return Currency::find($currencyCategoryId)->code;
        });
        $this->categoryCurrencyCode = $categoryCurrencyCode;

        $currencyOrganisationId = $this->organisation_currency_id;
        $organisationCurrencyCode = Cache::remember('currency_code_'.$currencyOrganisationId, 3600 * 24 * 30, function () use ($currencyOrganisationId) {
            return Currency::find($currencyOrganisationId)->code;
        });
        $this->organisationCurrencyCode = $organisationCurrencyCode;

        $columns = array_merge(
            [
                'label' => [
                    'formatted_value' => $this->name
                ]
            ],
            [
                'label_minified' => [
                    'formatted_value' => Abbreviate::run($this->name),
                    'tooltip'         => $this->name
                ]
            ],
            $this->getDashboardTableColumn($this, 'refunds'),
            $this->getDashboardTableColumn($this, 'refunds_minified'),
            $this->getDashboardTableColumn($this, 'refunds_delta'),
            $this->getDashboardTableColumn($this, 'invoices'),
            $this->getDashboardTableColumn($this, 'invoices_minified'),
            $this->getDashboardTableColumn($this, 'invoices_delta'),
            $this->getDashboardTableColumn($this, 'sales_invoice_category_currency'),
            $this->getDashboardTableColumn($this, 'sales_invoice_category_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_invoice_category_currency_delta'),
            $this->getDashboardTableColumn($this, 'sales_org_currency'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_minified'),
            $this->getDashboardTableColumn($this, 'sales_org_currency_delta'),
        );


        return [
            'slug'    => $this->slug,
            'state'   => $this->state == InvoiceCategoryStateEnum::ACTIVE ? 'active' : 'inactive',
            'columns' => $columns


        ];
    }
}
