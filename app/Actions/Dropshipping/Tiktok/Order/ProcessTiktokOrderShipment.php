<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-12h-32m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\TiktokUser;
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
    use WithActionUpdate;

    public function handle(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                $fulfillOrderId = $order->platform_order_id;
                $deliveryNote = $order->deliveryNotes->firstOrFail();

                /** @var TiktokUser $tiktokUser */
                $tiktokUser = $order->customerSalesChannel->user;

                $tiktokPackage = $tiktokUser->createOrderPackage($fulfillOrderId);
                $tiktokPackageId = Arr::get($tiktokPackage, 'data.package_id');
                $tiktokPackageDetail = $tiktokUser->getPackageDetail($tiktokPackageId);
                $tiktokPackageShippingId = Arr::get($tiktokPackageDetail, 'data.shipping_provider_id');
                $tiktokPackageShippingName = Arr::get($tiktokPackageDetail, 'data.shipping_provider_name');
                $tiktokPackageShippingCode = Str::slug(substr($tiktokPackageShippingName, 0, 8));
                $tiktokPackageTrackingNumber = Arr::get($tiktokPackageDetail, 'data.tracking_number');

                $tiktokShippingLabel = $tiktokUser->getOrderLabel($tiktokPackageId);
                $tiktokShippingLabelUrl = Arr::get($tiktokShippingLabel, 'data.doc_url');

                $shipper = Shipper::where('code', $tiktokPackageShippingCode)->first();

                if (!$shipper) {
                    $shipper = StoreShipper::make()->action($order->organisation, [
                        'code' => $tiktokPackageShippingCode,
                        'name' => $tiktokPackageShippingName,
                        'trade_as' => $tiktokPackageShippingName
                    ]);
                }

                StoreShipment::make()->action($deliveryNote, $shipper, [
                    'reference' => $tiktokPackageShippingId,
                    'tracking' => $tiktokPackageTrackingNumber,
                    'combined_label_url' => $tiktokShippingLabelUrl
                ]);

                $tiktokUser->shipPackage($tiktokPackageId);
            });
        } catch (\Throwable $th) {
            \Sentry::captureException($th);
            Log::error($th->getMessage());
        }
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisation($deliveryNote->organisation, $request);

        $this->handle($deliveryNote->orders->firstOrFail());
    }

    public $commandSignature = 'tiktok:order_shipment {order}';

    public function asCommand(Command $command): void
    {
        $this->handle(Order::where('slug', $command->argument('order'))->firstOrFail());
    }
}
