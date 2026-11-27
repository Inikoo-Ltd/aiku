<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Production\JobOrder\FillToOrder;
use App\Actions\Production\PartnerShippingList\AssignToProduceLinesAutomatically;
use App\Actions\Production\PartnerShippingList\StoreToProduceItemsFromOrder;
use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Actions\Comms\Email\SendNewOrderEmailToSubscribers;
use App\Actions\Comms\WhatsappOutbox\SendCustomerNotificationWhatsapp;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDeliveryNoteItemsSalesType;
use App\Actions\Dispatching\DeliveryNote\ReusePicksFromCancelledDeliveryNote;
use App\Actions\Dispatching\CrossDispatch\ResolveCrossDispatch;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\FulfilmentGate\GetGateCoverage;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\Ordering\Order\AssignDefaultPackagingToOrderWithoutPackaging;
use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SendOrderToWarehouse extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;

    /** Fixed and never translated, so the notes are found and removed whatever language wrote them */
    public const string HELD_MARKER = '⚠️ Order held:';

    public const string SENT_WITHOUT_AN_ADDRESS_MARKER = 'Sent without an address on purpose';

    public const string SENT_DESPITE_HOLD_MARKER = 'Sent to the warehouse despite the hold';

    public const string TO_FILL_MARKER = 'Bottles still being filled by production:';

    /** Set from the "Send anyway" button of the held bar: staff take responsibility for the missing bottles or SKOs (HELP-4969) */
    private bool $despiteHold = false;

    private bool $releaseFromGate = false;

    /** Staff pressed a send to warehouse button: that is the office approval of a held export order (HELP-4961) */
    private bool $officeRelease = false;

    /** Set from the "send it anyway" button: the customer could not be reached for an address */
    private bool $withoutAnAddress = false;

    /** INI-484. The company whose warehouse gets the note when it is not the one the order was placed in; only RaiseCrossDispatchDeliveryNote sets it */
    private ?Organisation $dispatcher = null;


    /**
     * A customer can pay and submit before anyone notices the address is empty, and the payment must never
     * be refused at that point (HELP-3102). The order is held in submitted instead, so nothing is picked and
     * no invoice prints a "0" street, until CS gets the address from the customer.
     */
    private function missingAddress(Order $order): bool
    {
        /** A decision to send it without an address survives the fulfilment gate and any later retry */
        if ($this->withoutAnAddress || str_contains((string)$order->private_warehouse_note, self::SENT_WITHOUT_AN_ADDRESS_MARKER)) {
            return false;
        }

        if (!$order->isMissingARequiredAddress()) {
            return false;
        }

        /** Replaces any earlier held warning instead of stacking a second one */
        $note = collect([
            self::withoutHeldNote($order->private_warehouse_note),
            self::HELD_MARKER.' '.__('the :address is missing, ask the customer for it before picking', ['address' => $order->missingRequiredAddress()]),
        ])->filter()->implode(' — ');

        if ($note !== $order->private_warehouse_note) {
            $this->update($order, ['private_warehouse_note' => $note]);
        }

        return true;
    }

    /**
     * A product sold before its SKOs were set gives the picker a delivery note with no lines, and the order
     * is dispatched without moving stock (HELP-4418). Held in submitted until the composition is fixed.
     */
    private function missingComposition(Order $order): bool
    {
        if ($this->sentDespiteHold($order)) {
            return false;
        }

        $productCodes = $this->productCodesWithoutSkos($order);

        if ($productCodes->isEmpty()) {
            return false;
        }

        $note = collect([
            self::withoutHeldNote($order->private_warehouse_note),
            self::HELD_MARKER.' '.__(':products have no SKOs, fix the product composition before sending it to the warehouse', ['products' => $productCodes->implode(', ')]),
        ])->filter()->implode(' — ');

        if ($note !== $order->private_warehouse_note) {
            $this->update($order, ['private_warehouse_note' => $note]);
        }

        return true;
    }

    /** Bottles filled to order hold the whole order until the artisan has filled them; FillToOrder::release sends it (HELP-4556) */
    private function waitingForFilling(Order $order): bool
    {
        if ($this->sentDespiteHold($order)) {
            return false;
        }

        if (StoreToProduceItemsFromOrder::make()->handle($order, fillsOnly: true)) {
            DB::afterCommit(fn () => rescue(fn () => AssignToProduceLinesAutomatically::run($order)));
        }

        $lines = FillToOrder::make()->pendingLines($order);
        if ($lines->isEmpty() || self::sendsBeforeFilled($order->shop)) {
            $toFill = $lines->isEmpty() ? null : self::TO_FILL_MARKER.' '.$lines->map(fn ($line) => $line->transaction->model->code)->unique()->implode(', ');
            $note   = collect(explode(' — ', (string)self::withoutHeldNote($order->private_warehouse_note)))
                ->reject(fn (string $part) => str_starts_with($part, self::TO_FILL_MARKER))
                ->push($toFill)->filter()->implode(' — ');
            if (($note ?: null) !== $order->private_warehouse_note && ($lines->isNotEmpty() || str_contains((string)$order->private_warehouse_note, self::TO_FILL_MARKER))) {
                $this->update($order, ['private_warehouse_note' => $note ?: null]);
            }

            return false;
        }

        $note = collect([
            self::withoutHeldNote($order->private_warehouse_note),
            self::HELD_MARKER.' '.__('waiting for filling: :products', ['products' => $lines->map(fn ($line) => $line->transaction->model->code)->unique()->implode(', ')]),
        ])->filter()->implode(' — ');

        if ($note !== $order->private_warehouse_note) {
            $this->update($order, ['private_warehouse_note' => $note]);
        }

        return true;
    }

    /**
     * HELP-4961. Export orders from new customers that fail the office checks used to be picked and then cancelled.
     * A first order of a customer to the countries in the organisation settings, or any order staff marked
     * "Do not pick", waits in submitted; the office releases it with "Send to warehouse".
     */
    private function awaitingOfficeApproval(Order $order): bool
    {
        $reason = $this->officeHoldReason($order);
        if (!$reason) {
            return false;
        }

        $note = collect([
            self::withoutHeldNote($order->private_warehouse_note),
            self::HELD_MARKER.' '.$reason,
        ])->filter()->implode(' — ');

        if ($note !== $order->private_warehouse_note) {
            $this->update($order, ['private_warehouse_note' => $note]);
        }

        return true;
    }

    /** Why the order waits for the office before the warehouse, also read live by the order page; null when it does not */
    public function officeHoldReason(Order $order): ?string
    {
        if (!$this->dispatcher && Arr::get($order->data, 'do_not_pick')) {
            return __('do not pick, the office must check it and send it to the warehouse');
        }

        $countryCode = $this->officeApprovalCountry($order);
        if (!$countryCode || Arr::get($order->data, 'office_released_country') === $countryCode) {
            return null;
        }

        return Arr::get($order->organisation->settings, 'order_release.new_customers_only', true)
            ? __('new customer, export to :country, the office must check it and send it to the warehouse', ['country' => $countryCode])
            : __('export to :country, the office must check it and send it to the warehouse', ['country' => $countryCode]);
    }

    /** The delivery country when the organisation makes new customers' orders to it wait for the office, otherwise null */
    private function officeApprovalCountry(Order $order): ?string
    {
        if ($this->dispatcher || $this->releaseFromGate || $order->collection_address_id) {
            return null;
        }

        $countryCodes = Arr::get($order->organisation->settings, 'order_release.hold_countries') ?: [];
        $countryCode  = $order->deliveryAddress?->country_code;
        if (!$countryCodes || !$countryCode || in_array($countryCode, $countryCodes, true) === (bool)Arr::get($order->organisation->settings, 'order_release.except_countries', false)) {
            return null;
        }

        $newCustomersOnly = (bool)Arr::get($order->organisation->settings, 'order_release.new_customers_only', true);

        return !$newCustomersOnly || $this->isNewCustomer($order) ? $countryCode : null;
    }

    /** No earlier order of the customer was invoiced or dispatched: regular customers and partners go straight to the warehouse */
    private function isNewCustomer(Order $order): bool
    {
        return $order->customer->invoices()->where('type', InvoiceTypeEnum::INVOICE)->whereNull('deleted_at')->doesntExist()
            && $order->customer->orders()
                ->whereKeyNot($order->id)
                ->whereIn('state', [OrderStateEnum::FINALISED, OrderStateEnum::DISPATCHED])
                ->doesntExist();
    }

    /** Recorded before any other hold, so an order the office released is not held for it again once filling or an address clears; a new delivery country needs a new release */
    private function recordOfficeRelease(Order $order): void
    {
        if ($this->officeRelease && Arr::get($order->data, 'do_not_pick')) {
            $this->update($order, ['data' => array_merge($order->data ?? [], ['do_not_pick' => false])]);
        }

        $countryCode = $this->officeApprovalCountry($order);
        if (!$this->officeRelease || !$countryCode || Arr::get($order->data, 'office_released_country') === $countryCode) {
            return;
        }

        $this->update($order, ['data' => array_merge($order->data ?? [], [
            'office_released_country' => $countryCode,
            'office_released_at'      => now()->toIso8601String(),
        ])]);
    }

    /** HELP-4985: on by default, so an order goes to the warehouse while production fills its bottles; a shop can switch it off to hold the order until filled */
    public static function sendsBeforeFilled(Shop $shop): bool
    {
        return (bool)Arr::get($shop->settings, 'orders.send_before_filled', true);
    }

    private function sentDespiteHold(Order $order): bool
    {
        return $this->despiteHold || str_contains((string)$order->private_warehouse_note, self::SENT_DESPITE_HOLD_MARKER);
    }

    /** @return Collection<int, string> */
    private function productCodesWithoutSkos(Order $order): Collection
    {
        return Product::whereIn('id', $order->transactions()->where('model_type', 'Product')->select('model_id'))
            ->whereDoesntHave('orgStocks')
            ->pluck('code');
    }

    /** Read live for the order page: the holds only the bottles being filled or a fixed product composition can clear */
    public function blockedReason(Order $order): ?string
    {
        if ($order->state != OrderStateEnum::SUBMITTED || $this->sentDespiteHold($order)) {
            return null;
        }

        $productCodes = $this->productCodesWithoutSkos($order);
        if ($productCodes->isNotEmpty()) {
            return __(':products have no SKOs, fix the product composition before sending it to the warehouse', ['products' => $productCodes->implode(', ')]);
        }

        $lines = FillToOrder::make()->pendingLines($order);
        if ($lines->isNotEmpty() && !self::sendsBeforeFilled($order->shop)) {
            return __('waiting for filling: :products', ['products' => $lines->map(fn ($line) => $line->transaction->model->code)->unique()->implode(', ')]);
        }

        return null;
    }

    /** Why the order is held, read from its warehouse note: "waiting for filling: FOKG-160-10ml" */
    public static function heldReason(?string $note): ?string
    {
        if (!preg_match('/'.preg_quote(self::HELD_MARKER, '/').'\s*(.*?)(?=\s—\s|\R|$)/u', (string)$note, $match)) {
            return null;
        }

        return trim($match[1]) ?: null;
    }

    /** The note without the held warning, keeping whatever staff wrote around it */
    public static function withoutHeldNote(?string $note): ?string
    {
        $cleaned = preg_replace('/'.preg_quote(self::HELD_MARKER, '/').'.*?(?=\s—\s|\R|$)/u', '', (string)$note);
        $cleaned = preg_replace('/(\s*—\s*){2,}/u', ' — ', $cleaned);
        $cleaned = trim(preg_replace('/^[\s—]+|[\s—]+$/u', '', $cleaned));

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): ?DeliveryNote
    {
        /** A pre-order waits for its goods and its balance; ReleasePreOrder sends it (HELP-3432) */
        if ($order->preOrder && in_array($order->preOrder->state, PreOrderStateEnum::open())) {
            if (!$this->asAction) {
                throw ValidationException::withMessages([
                    'order' => __('This pre-order is waiting for its goods or its balance. Release it from the pre-order panel.'),
                ]);
            }

            return null;
        }

        data_set($modelData, 'state', OrderStateEnum::IN_WAREHOUSE);

        if ($this->releaseFromGate && $order->at_gate_at) {
            $this->update($order, ['at_gate_at' => null]);
        }
        $date = now();


        $warehouseId = Arr::pull($modelData, 'warehouse_id');
        data_forget($modelData, 'warehouse_id');

        if ($order->state == OrderStateEnum::SUBMITTED || $order->in_warehouse_at == null) {
            data_set($modelData, 'in_warehouse_at', $date);
        }

        if (!$order->transactions()->where('model_type', 'Product')->exists()
            && $order->transactions()->where('model_type', 'Service')->exists()) {
            if ($order->customer) {
                $modelData['email']        = $order->customer->email;
                $modelData['phone']        = $order->customer->phone;
                $modelData['contact_name'] = $order->customer->contact_name;
                $modelData['company_name'] = $order->customer->company_name;
            }
            UpdateOrder::make()->action($order, $modelData);

            FinaliseOrder::make()->action($order, true);
            DispatchOrder::make()->action($order, null);

            if (in_array($order->salesChannel?->type, [
                SalesChannelTypeEnum::PHONE,
                SalesChannelTypeEnum::SHOWROOM,
                SalesChannelTypeEnum::EMAIL,
                SalesChannelTypeEnum::OTHER
            ])) {
                SendNewOrderEmailToSubscribers::dispatch($order->id);
                SendNewOrderEmailToCustomer::dispatch($order->id);
                SendCustomerNotificationWhatsapp::dispatch($order->customer, OutboxCodeEnum::ORDER_CONFIRMATION, $order);
            }

            return null;
        }


        $this->recordOfficeRelease($order);

        if ($this->missingAddress($order) || $this->missingComposition($order) || $this->waitingForFilling($order) || $this->awaitingOfficeApproval($order)) {
            return null;
        }

        /** Released: the warning goes, so it is not copied onto the delivery note the picker reads */
        if (str_contains((string)$order->private_warehouse_note, self::HELD_MARKER)) {
            $this->update($order, ['private_warehouse_note' => self::withoutHeldNote($order->private_warehouse_note)]);
        }

        if (!$this->dispatcher
            && !$this->releaseFromGate
            && $order->organisation->hasFulfilmentGate()
            && !GetGateCoverage::make()->isFullyCoverable($order)
        ) {
            if (!$order->at_gate_at) {
                $this->update($order, ['at_gate_at' => $date]);
            }

            return null;
        }

        $deliveryNoteData = [
            'delivery_address'          => $order->deliveryAddress,
            'date'                      => $date,
            'reference'                 => $order->reference,
            'state'                     => DeliveryNoteStateEnum::UNASSIGNED,
            'submitted_at'              => now(),
            'warehouse_id'              => $warehouseId,
            'customer_client_id'        => $order->customer_client_id,
            'customer_sales_channel_id' => $order->customer_sales_channel_id,
            'platform_id'               => $order->platform_id,
            'email'                     => $this->getEmail($order),
            'phone'                     => $this->getPhone($order),
            'company_name'              => $this->getCompanyName($order),
            'contact_name'              => $this->getContactName($order),
            'shipping_zone_schema_id'   => $order->shipping_zone_schema_id,
            'shipping_zone_id'          => $order->shipping_zone_id,
            'is_shipping_by_external'   => $order->is_shipping_by_external,
            'private_warehouse_note'    => $order->private_warehouse_note

        ];

        $deliveryNote = DB::transaction(function () use ($order, $deliveryNoteData, $date) {
            /** @var Transaction $transactions */
            $transactions = $order->transactions()->whereIn('state', [TransactionStateEnum::CREATING, TransactionStateEnum::SUBMITTED])->get();
            foreach ($transactions as $transaction) {
                $transactionData = [
                    'state'           => TransactionStateEnum::IN_WAREHOUSE,
                    'status'          => TransactionStatusEnum::PROCESSING,
                    'in_warehouse_at' => now()
                ];
                if ($transaction->in_warehouse_at == null) {
                    data_set($transactionData, 'in_warehouse_at', $date);
                }
                $transaction->update($transactionData);
            }

            $deliveryNote = StoreDeliveryNote::make()->action($order, $deliveryNoteData, dispatcher: $this->dispatcher);

            $transactions = $order->transactions()->where('model_type', 'Product')->get();


            /** @var Transaction $transaction */
            foreach ($transactions as $transaction) {
                $product = Product::find($transaction->model_id);


                foreach (ResolveCrossDispatch::make()->orgStocksForDeliveryNote($deliveryNote, $product->orgStocks) as $orgStock) {
                    $quantity             = DeliveryNoteItem::requiredQuantity((float) $orgStock->pivot->quantity, (float) $transaction->quantity_ordered + (float) $transaction->quantity_bonus);
                    $deliveryNoteItemData = [
                        'org_stock_id'               => $orgStock->id,
                        'transaction_id'             => $transaction->id,
                        'quantity_required'          => $quantity,
                        'original_quantity_required' => $quantity,
                    ];

                    StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteItemData);
                }
            }


            return $deliveryNote;
        });

        DeliveryNoteHydrateDeliveryNoteItemsSalesType::run($deliveryNote);

        ReusePicksFromCancelledDeliveryNote::make()->action($order, $deliveryNote);

        /** An Orderchamp order is billed to Orderchamp and delivered to the retailer named on the order */
        if ($order->customer && !$order->isFromOrderchamp()) {
            $modelData['email']        = $order->customer->email;
            $modelData['phone']        = $order->customer->phone;
            $modelData['contact_name'] = $order->customer->contact_name;
            $modelData['company_name'] = $order->customer->company_name;
        }
        UpdateOrder::make()->action($order, $modelData);

        if (AssignDefaultPackagingToOrderWithoutPackaging::run($order)) {
            $deliveryNote->refresh();
        }

        if (in_array($order->salesChannel?->type, [
            SalesChannelTypeEnum::PHONE,
            SalesChannelTypeEnum::SHOWROOM,
            SalesChannelTypeEnum::EMAIL,
            SalesChannelTypeEnum::OTHER
        ])) {
            $sendConfirmations = function () use ($order) {
                SendNewOrderEmailToSubscribers::dispatch($order->id);
                SendNewOrderEmailToCustomer::dispatch($order->id);
                SendCustomerNotificationWhatsapp::dispatch($order->customer, OutboxCodeEnum::ORDER_CONFIRMATION, $order);
            };
            /** INI-484. A cross dispatch is raised inside a transaction that can still refuse it, so nothing is sent before it commits */
            $this->dispatcher ? DB::afterCommit($sendConfirmations) : $sendConfirmations();
        }

        return $deliveryNote;
    }

    public function getEmail(Order $order): ?string
    {
        $email = '';
        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            if ($order->customerClient) {
                $email = $order->customerClient->email;
            }
        } else {
            $email = $order->customer->email;
        }

        return $email;
    }

    public function getPhone(Order $order): ?string
    {
        $phone = '';
        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            if ($order->customerClient) {
                $phone = $order->customerClient->phone;
            }
        } else {
            $phone = $order->isFromOrderchamp() && $order->phone ? $order->phone : $order->customer->phone;
        }

        return $phone;
    }

    public function getCompanyName(Order $order): ?string
    {
        $companyName = '';
        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            if ($order->customerClient) {
                $companyName = $order->customerClient->company_name;
            }
        } else {
            $companyName = $order->company_name ?? $order->customer->company_name;
        }

        return $companyName;
    }

    public function getContactName(Order $order): ?string
    {
        $contactName = '';
        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            if ($order->customerClient) {
                $contactName = $order->customerClient->contact_name;
            }
        } else {
            $contactName = $order->contact_name ?? $order->customer->contact_name;
        }

        return Str::substr($contactName, 0, 40);
    }


    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', ($this->dispatcher ?? $this->organisation)->id),
            ],
            'without_an_address' => ['sometimes', 'boolean'],
            'despite_hold'       => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('warehouse_id')) {
            /** @var Warehouse $warehouse */
            $warehouse = ($this->dispatcher ?? $this->shop->organisation)->warehouses()->first();
            if ($warehouse) {
                $this->set('warehouse_id', $warehouse->id);
            }
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CREATING) {
            $validator->errors()->add('state', __('Only submitted orders can be send to warehouse'));
        } elseif ($this->order->state == OrderStateEnum::SUBMITTED && !$this->order->transactions->count()) {
            $validator->errors()->add('state', __('Order dont have any transactions to be send to warehouse'));
        } elseif ($this->order->state == OrderStateEnum::IN_WAREHOUSE || $this->order->state == OrderStateEnum::HANDLING || $this->order->state == OrderStateEnum::PACKED) {
            $validator->errors()->add('state', __('Order already in warehouse'));
        } elseif ($this->order->state == OrderStateEnum::FINALISED) {
            $validator->errors()->add('state', __('Order is already finalised'));
        } elseif ($this->order->state == OrderStateEnum::DISPATCHED) {
            $validator->errors()->add('state', __('Order is already dispatched'));
        } elseif ($this->order->state == OrderStateEnum::CANCELLED) {
            $validator->errors()->add('state', __('Order has been cancelled'));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData, bool $releaseFromGate = false, bool $withoutAnAddress = false, ?Organisation $dispatcher = null, bool $officeRelease = false): ?DeliveryNote
    {
        $this->dispatcher       = $dispatcher?->id === $order->organisation_id ? null : $dispatcher;
        if ($this->dispatcher) {
            ResolveCrossDispatch::ensureDirectionEnabled($order->organisation_id, $this->dispatcher->id);
        }
        $this->asAction         = true;
        $this->releaseFromGate  = $releaseFromGate;
        $this->withoutAnAddress = $withoutAnAddress;
        $this->officeRelease    = $officeRelease;
        $this->order            = $order;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): ?DeliveryNote
    {
        $this->order         = $order;
        $this->officeRelease = true;
        $this->initialisationFromShop($order->shop, $request);

        $this->withoutAnAddress = (bool)Arr::pull($this->validatedData, 'without_an_address', false);
        $despiteHold            = (bool)Arr::pull($this->validatedData, 'despite_hold', false);

        if ($despiteHold && $blockedReason = $this->blockedReason($order)) {
            $this->update($order, [
                'private_warehouse_note' => collect([
                    self::withoutHeldNote($order->private_warehouse_note),
                    self::SENT_DESPITE_HOLD_MARKER.' ('.$blockedReason.') '.__('by :user', ['user' => $request->user()->contact_name ?: $request->user()->username]),
                ])->filter()->implode(' — ')
            ]);
        }
        $this->despiteHold = $despiteHold;

        /** Otherwise the ordinary button quietly does nothing and the order sits there (HELP-3102) */
        if (!$this->withoutAnAddress && $order->isMissingARequiredAddress()) {
            $request->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('This order has no :address', ['address' => $order->missingRequiredAddress()]),
                'description' => __('Add an address to the order or to the customer, or use "Send anyway, no address".'),
            ]);

            return null;
        }

        if ($this->withoutAnAddress && !str_contains((string)$order->private_warehouse_note, self::SENT_WITHOUT_AN_ADDRESS_MARKER)) {
            $this->update($order, [
                'private_warehouse_note' => collect([
                    self::withoutHeldNote($order->private_warehouse_note),
                    self::SENT_WITHOUT_AN_ADDRESS_MARKER.', '.__('the customer could not be reached'),
                ])->filter()->implode(' — ')
            ]);
        }

        return $this->handle($order, $this->validatedData);
    }

    /** Inertia needs a page back, otherwise the flashed message is never rendered */
    public function htmlResponse(?DeliveryNote $deliveryNote, ActionRequest $request): RedirectResponse
    {
        return Redirect::back();
    }

    public function getCommandSignature(): string
    {
        return 'order:send-to-warehouse {order}';
    }

    public function getCommandDescription(): string
    {
        return 'Send order to warehouse';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $order               = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->officeRelease = true;

        $this->handle($order, [
            'warehouse_id' => $order->organisation->warehouses()->first()->id
        ]);

        return 0;
    }
}
