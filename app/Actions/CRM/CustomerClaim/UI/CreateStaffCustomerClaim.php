<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The staff claim form behind + Claim on an order: the lines still left to claim, every reason, and
 * the outcomes the order allows, plus an invoice refund for money that is not about items.
 */
class CreateStaffCustomerClaim extends OrgAction
{
    use WithCustomerClaimsSection;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["crm.{$this->shop->id}.edit", "orders.{$this->shop->id}.edit"]);
    }

    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $form       = GetCustomerClaimForm::run($order->customer, $order);
        $hasInvoice = $order->invoices()->where('type', InvoiceTypeEnum::INVOICE)->exists();
        $outcomes   = array_values(array_filter([
            ...($form['outcomes'] ?? []),
            $hasInvoice ? 'invoice_refund' : null,
        ]));

        return Inertia::render('Org/Shop/CRM/CreateCustomerClaim', [
            'breadcrumbs' => $this->claimsBreadcrumbs($order->shop, ['route' => ['name' => $request->route()->getName(), 'parameters' => $request->route()->originalParameters()], 'label' => __('New claim')]),
            'title'       => __('New claim'),
            'pageHead'    => [
                'title' => __('New claim for :order', ['order' => $order->reference]),
                'model' => $order->customer->name,
                'icon'  => ['icon' => ['fal', 'fa-box-fragile'], 'title' => __('Claim')],
            ],
            'order'       => ['reference' => $order->reference, 'route' => ['name' => 'grp.org.shops.show.ordering.orders.show', 'parameters' => [$order->organisation->slug, $order->shop->slug, $order->slug]]],
            'customer'    => ['name' => $order->customer->name, 'claims' => CustomerClaim::where('customer_id', $order->customer_id)->count()],
            'lines'       => $form['lines'] ?? [],
            'too_old'     => (bool) ($form['too_old'] ?? false),
            'reasons'     => collect(DeliveryNoteItemReplacementReasonEnum::cases())->map(fn (DeliveryNoteItemReplacementReasonEnum $reason) => ['value' => $reason->value, 'label' => $reason->label()])->all(),
            'outcomes'    => collect($outcomes)->map(fn (string $outcome) => ['value' => $outcome, 'label' => ShowCustomerClaim::resolutionLabel($outcome)])->all(),
            'currency_code' => $order->currency?->code,
            'store_route' => ['name' => 'grp.models.order.customer_claim.store', 'parameters' => ['order' => $order->id]],
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        abort_unless($order->shop_id === $shop->id, 404);
        $this->initialisationFromShop($shop, $request);

        return $order;
    }
}
