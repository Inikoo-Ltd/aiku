<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 11 May 2026 14:25:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Order;

use App\Actions\Traits\WithRetinaRouteModelOwnershipCheck;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipper\StoreShipper;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ProcessTiktokOrderShipment extends OrgAction
{
    use WithRetinaRouteModelOwnershipCheck;
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order|PalletReturn $order, ?DeliveryNote $deliveryNote = null): void
    {
        $shipmentParent = $order instanceof PalletReturn
            ? $order
            : ($deliveryNote ?? $order->deliveryNotes->firstOrFail());

        if ($shipmentParent->shipments()->exists()) {
            return;
        }

        DB::transaction(function () use ($order, $shipmentParent) {
            $fulfillOrderId = $order->platform_order_id;

            /** @var TiktokUser $tiktokUser */
            $tiktokUser = $order->customerSalesChannel->user;

            $getOrder = $this->ensureTiktokAnswered($tiktokUser->getOrder($fulfillOrderId));

            $id = Arr::get($getOrder, 'data.orders.0.packages.0.id');
            $status = Arr::get($getOrder, 'data.orders.0.status');

            if ($id && $status === "AWAITING_COLLECTION") {
                $this->packageWasShipped($tiktokUser, $order, $shipmentParent, $id);

                return;
            }

            $tiktokPackage = $this->ensureTiktokAnswered($tiktokUser->createOrderPackage($fulfillOrderId));
            $tiktokPackageId = Arr::get($tiktokPackage, 'data.package_id');

            if (!$tiktokPackageId) {
                throw ValidationException::withMessages([
                    'message' => __('TikTok did not create a package for this order.')
                ]);
            }

            $tiktokPackageDetail = $this->ensureTiktokAnswered($tiktokUser->getPackageDetail($tiktokPackageId));

            $tiktokShippingLabel = $this->ensureTiktokAnswered($tiktokUser->getOrderLabel($tiktokPackageId));
            $tiktokShippingLabelUrl = Arr::get($tiktokShippingLabel, 'data.doc_url');

            $this->processShipment(
                $order,
                $shipmentParent,
                $tiktokPackageDetail,
                $tiktokShippingLabelUrl
            );

            $tiktokUser->shipPackage($tiktokPackageId);
        });
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureTiktokAnswered(array $response): array
    {
        if (Arr::get($response, 'error')) {
            throw ValidationException::withMessages([
                'message' => __('TikTok did not return the shipment: :error', ['error' => Arr::get($response, 'data')])
            ]);
        }

        return $response;
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
        $tiktokPackageDetail = $this->ensureTiktokAnswered($tiktokUser->getPackageDetail($tiktokPackageId));

        $tiktokShippingLabel = $this->ensureTiktokAnswered($tiktokUser->getOrderLabel($tiktokPackageId));
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
        $tiktokPackageTrackingNumber = Arr::get($tiktokPackageDetail, 'data.tracking_number');

        $shipper = $this->getShipper($order->organisation, $tiktokPackageShippingName);

        StoreShipment::make()->action($deliveryNote, $shipper, [
            'reference' => $tiktokPackageShippingId,
            'tracking' => $tiktokPackageTrackingNumber,
            'combined_label_url' => $tiktokShippingLabelUrl
        ]);
    }

    /**
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getShipper(Organisation $organisation, string $tiktokPackageShippingName): Shipper
    {
        $tiktokPackageShippingCode = Str::slug(substr($tiktokPackageShippingName, 0, 8));

        $shipper = Shipper::where('organisation_id', $organisation->id)
            ->where('code', $tiktokPackageShippingCode)
            ->first();

        if (!$shipper) {
            $shipper = StoreShipper::make()->action($organisation, [
                'code' => $tiktokPackageShippingCode,
                'name' => $tiktokPackageShippingName.' (TikTok)',
                'trade_as' => Str::substr($tiktokPackageShippingName, 0, 15)
            ]);
        }

        return $shipper;
    }

    public function authorize(ActionRequest $request): bool
    {
        $deliveryNote = $request->route('deliveryNote');
        if ($deliveryNote instanceof DeliveryNote && $request->user() instanceof User) {
            return $deliveryNote->canBeShippedBy($request->user());
        }

        return true;
    }

    public function asController(ActionRequest $request, DeliveryNote $deliveryNote)
    {
        $this->initialisation($deliveryNote->organisation, $request);

        $this->handle($deliveryNote->orders->firstOrFail(), $deliveryNote);
    }

    public function inRetinaFulfilment(ActionRequest $request, PalletReturn $palletReturn)
    {
        abort_unless($this->retinaCustomerOwnsRouteModels($request), 403);

        $this->inFulfilment($request, $palletReturn);
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
