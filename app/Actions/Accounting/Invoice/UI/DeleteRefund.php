<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\Accounting\Invoice\Traits\WithDeleteInvoiceUI;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class DeleteRefund extends OrgAction
{
    use WithActionUpdate;
    use WithDeleteInvoiceUI;

    public function handle(Invoice $refund, array $modelData): Invoice
    {
        return DeleteInvoice::run($refund, $modelData);
    }

    public function rules(): array
    {
        return [
            'deleted_note' => ['sometimes', 'string', 'max:4000'],
            'deleted_by'   => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->group->id)],
            'source'       => ['sometimes', 'string', Rule::in('customer', 'dashboard', '')],
        ];
    }

    public function htmlResponse(Invoice $refund): RedirectResponse
    {
        if ($refund->shop->type == ShopTypeEnum::FULFILMENT) {
            return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
                $refund->organisation->slug,
                $refund->customer->fulfilmentCustomer->fulfilment->slug,
                $refund->customer->fulfilmentCustomer->slug,
                'tab' => InvoicesTabsEnum::REFUNDS->value
            ]);
        }

        return Redirect::route('grp.org.shops.show.dashboard.invoices.refunds.index', [
            $refund->organisation->slug,
            $refund->shop->slug
        ])->with(
            'notification',
            [
                'status' => 'success',
                'title' => __('Refund was successfully deleted!'),
                'message' => __('Refund was successfully deleted')
            ]
        );
    }

    public string $commandSignature = 'invoice:refund {slug} {--deleted_note= : Reason for deletion} {--deleted_by= : User who deleted the refund}';
}
