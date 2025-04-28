<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 13:48:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithInvoicesSubNavigation;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Http\Resources\Accounting\RefundsResource;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexFulfilmentRefunds extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithCustomerSubNavigation;
    use WithInvoicesSubNavigation;

    private Invoice|Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|InvoiceCategory|Shop $parent;
    private string $bucket;

    public function handle(Invoice|Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|InvoiceCategory|Shop|Order $parent, $prefix = null): LengthAwarePaginator
    {
        return IndexRefunds::run($parent, $prefix);
    }

    public function tableStructure(Invoice|Group|Organisation|Fulfilment|Customer|CustomerClient|FulfilmentCustomer|InvoiceCategory|Shop|Order $parent, $prefix = null): Closure
    {
        return IndexRefunds::make()->tableStructure($parent, $prefix);
    }


    public function htmlResponse(LengthAwarePaginator $refunds, ActionRequest $request): Response
    {
        $subNavigation = [];

        if ($this->parent instanceof CustomerClient) {
            $subNavigation = $this->getCustomerClientSubNavigation($this->parent);
        } elseif ($this->parent instanceof Customer) {
            if ($this->parent->is_dropshipping) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            }
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        } elseif ($this->parent instanceof Shop || $this->parent instanceof Fulfilment || $this->parent instanceof Organisation) {
            $subNavigation = $this->getInvoicesNavigation($this->parent);
        }


        $title = __('Refunds');

        $icon = [
            'icon'  => ['fal', 'fa-file-invoice-dollar'],
            'title' => __('refunds')
        ];

        $afterTitle = null;
        $iconRight  = null;
        $model      = null;
        $actions    = null;

        if ($this->parent instanceof FulfilmentCustomer) {
            $icon       = ['fal', 'fa-user'];
            $title      = $this->parent->customer->name;
            $iconRight  = [
                'icon' => 'fal fa-file-invoice-dollar',
            ];
            $afterTitle = [

                'label' => __('invoices')
            ];
        } elseif ($this->parent instanceof CustomerClient) {
            $iconRight  = $icon;
            $afterTitle = [
                'label' => $title
            ];

            $title = $this->parent->name;
            $model = __('customer client');
            $icon  = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('customer client')
            ];
        } elseif ($this->parent instanceof Customer) {
            $iconRight  = $icon;
            $afterTitle = [
                'label' => $title
            ];
            $title      = $this->parent->name;
            $icon       = [
                'icon'  => ['fal', 'fa-user'],
                'title' => __('customer')
            ];
        } elseif ($this->parent instanceof Invoice) {

            $afterTitle = [
                'label' => __('Refunds')
            ];
            $iconRight  = [
                'icon' => 'fal fa-file-minus',
            ];
            $title      = $this->parent->reference;

        }

        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Accounting/Refunds',
            [
                'breadcrumbs' => IndexRefunds::make()->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('invoices'),
                'pageHead'    => [

                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => RefundsResource::collection($refunds),


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'paid';
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function unpaidInShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'unpaid';
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function unpaidFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'unpaid';
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    public function paidFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'paid';
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    public function inInvoiceInOrganisation(Organisation $organisation, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'invoice';
        $this->parent = $invoice;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function allFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentInvoice(Organisation $organisation, Fulfilment $fulfilment, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($invoice);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inInvoiceInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $invoice;
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($invoice);
    }




    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $customer);
    }



}
