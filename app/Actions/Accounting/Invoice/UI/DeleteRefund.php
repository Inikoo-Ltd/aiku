<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\DeleteInvoice;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Models\Accounting\Invoice;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class DeleteRefund extends OrgAction
{
    use WithActionUpdate;

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

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund, $this->validatedData);
    }

    public function action(Invoice $refund, array $modelData): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromShop($refund->shop, $modelData);

        return $this->handle($refund, $this->validatedData);
    }

    public string $commandSignature = 'invoice:refund {slug} {--deleted_note= : Reason for deletion} {--deleted_by= : User who deleted the refund}';


    public function asCommand(Command $command): int
    {
        $this->asAction = true;

        try {
            /** @var Invoice $refund */
            $refund = Invoice::where('slug', $command->argument('slug'))->firstOrFail();
        } catch (Exception $e) {
            $command->error($e->getMessage());

            return 1;
        }


        $modelData = [];

        if ($command->option('deleted_note')) {
            $modelData['deleted_note'] = $command->option('deleted_note');
        }
        if ($command->option('deleted_by')) {
            $modelData['deleted_by'] = $command->option('deleted_by');
        }

        $this->action($refund, $modelData);

        return 0;
    }


}
