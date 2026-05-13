<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 11 May 2026 14:25:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProcessTiktokOrderShipment extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order|PalletReturn $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                $fulfillOrderId = $order->platform_order_id;

                if ($order instanceof PalletReturn) {
                    $deliveryNote = $order;
                } else {
                    $deliveryNote = $order->deliveryNotes->firstOrFail();
                }

                /** @var TiktokUser $tiktokUser */
                $tiktokUser = $order->customerSalesChannel->user;

                $getOrder = $tiktokUser->getOrder($fulfillOrderId);

                $id = Arr::get($getOrder, 'data.orders.0.packages.0.id');
                $status = Arr::get($getOrder, 'data.orders.0.status');

                if ($id && $status === "AWAITING_COLLECTION") {
                    $this->packageWasShipped($tiktokUser, $order, $deliveryNote, $id);

                    return;
                }

                $tiktokPackage = $tiktokUser->createOrderPackage($fulfillOrderId);
                $tiktokPackageId = Arr::get($tiktokPackage, 'data.package_id');
                $tiktokPackageDetail = $tiktokUser->getPackageDetail($tiktokPackageId);

                $tiktokShippingLabel = $tiktokUser->getOrderLabel($tiktokPackageId);
                $tiktokShippingLabelUrl = Arr::get($tiktokShippingLabel, 'data.doc_url');

                $this->processShipment(
                    $order,
                    $deliveryNote,
                    $tiktokPackageDetail,
                    $tiktokShippingLabelUrl
                );

                $tiktokUser->shipPackage($tiktokPackageId);
            });
        } catch (\Throwable $th) {
            \Sentry::captureException($th);
            Log::error($th->getMessage());
        }
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function packageWasShipped(
        TiktokUser $tiktokUser,
        Order|PalletReturn $order,
        DeliveryNote|PalletReturn $deliveryNote,
        $tiktokPackageId
    ): void {
        $tiktokPackageDetail = $tiktokUser->getPackageDetail($tiktokPackageId);

        $tiktokShippingLabel = $tiktokUser->getOrderLabel($tiktokPackageId);
        $tiktokShippingLabelUrl = Arr::get($tiktokShippingLabel, 'data.doc_url');

        $this->processShipment(
            $order,
            $deliveryNote,
            $tiktokPackageDetail,
            $tiktokShippingLabelUrl
        );
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function processShipment(
        Order|PalletReturn $order,
        DeliveryNote|PalletReturn $deliveryNote,
        $tiktokPackageDetail,
        $tiktokShippingLabelUrl
    ): void {
        $tiktokPackageShippingId = Arr::get($tiktokPackageDetail, 'data.shipping_provider_id');
        $tiktokPackageShippingName = Arr::get($tiktokPackageDetail, 'data.shipping_provider_name');
        $tiktokPackageShippingCode = Str::slug(substr($tiktokPackageShippingName, 0, 8));
        $tiktokPackageTrackingNumber = Arr::get($tiktokPackageDetail, 'data.tracking_number');

        $shipper = Shipper::where('code', $tiktokPackageShippingCode)->first();

        if (!$shipper) {
            $shipper = StoreShipper::make()->action($order->organisation, [
                'code' => $tiktokPackageShippingCode,
                'name' => $tiktokPackageShippingName.' (TikTok)',
                'trade_as' => Str::substr($tiktokPackageShippingName, 0, 15)
            ]);
        }

        StoreShipment::make()->action($deliveryNote, $shipper, [
            'reference' => $tiktokPackageShippingId,
            'tracking' => $tiktokPackageTrackingNumber,
            'combined_label_url' => $tiktokShippingLabelUrl
        ]);
    }

    public function asController(ActionRequest $request, DeliveryNote $deliveryNote)
    {
        $this->initialisation($deliveryNote->organisation, $request);

        $this->handle($deliveryNote->orders->firstOrFail());
    }

    public function inFulfilment(ActionRequest $request, PalletReturn $palletReturn)
    {
        $this->initialisation($palletReturn->organisation, $request);

        $this->handle($palletReturn);
    }

    public $commandSignature = 'tiktok:order_shipment {order}';

    public function asCommand(Command $command): void
    {
        $this->handle(Order::where('slug', $command->argument('order'))->firstOrFail());
    }
}
