<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 10:48:35 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentGatewayLog;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PreProcessCheckoutComPaymentGatewayLog
{
    use asAction;

    public function handle(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $payload = $paymentGatewayLog->payload;

        $gatewayId        = Arr::get($payload, 'id');
        $gatewayPaymentId = Arr::get($payload, 'data.id');


        $paymentGatewayLog->update([
            'state'              => PaymentGatewayLogStateEnum::PREPROCESSING,
            'gateway_id'         => $gatewayId,
            'gateway_payment_id' => $gatewayPaymentId,
            'type'               => Arr::get($payload, 'type'),
            'gateway_date'       => Arr::get($payload, 'data.processed_on'),
        ]);

        /** Only defer to an OLDER log: when two deliveries of the same event race, the earliest
         * one always processes itself, so the event can never be dropped by mutual deferral */
        if ($duplicatedPaymentGatewayLog = PaymentGatewayLog::where('id', '<', $paymentGatewayLog->id)
            ->where('gateway_id', $gatewayId)
            ->orderBy('id')
            ->first()) {
            $paymentGatewayLog->update([
                'status'       => PaymentGatewayLogStatusEnum::DUPLICATED,
                'state'        => PaymentGatewayLogStateEnum::PROCESSED,
                'processed_at' => now(),
            ]);

            ProcessCheckoutComPaymentGatewayLog::run($duplicatedPaymentGatewayLog);

            return $paymentGatewayLog;
        }


        if (Arr::get($payload, 'data.metadata.origin') != 'aiku') {
            return $this->processAurora($paymentGatewayLog);
        }

        $environment = Arr::get($payload, 'data.metadata.environment');

        $paymentGatewayLog->update([
            'origin'      => 'aiku',
            'environment' => Arr::get($payload, 'data.metadata.environment'),
        ]);

        if (app()->isProduction() && $environment != 'production') {
            return $paymentGatewayLog;
        }

        if (!app()->isProduction() && $environment == 'production') {
            return $paymentGatewayLog;
        }

        if (!app()->isProduction()) {
            $eventServer = Arr::get($payload, 'data.metadata.server');
            if (!$eventServer || !config('app.server_name') || $eventServer != config('app.server_name')) {
                return $paymentGatewayLog;
            }
        }

        $paymentGatewayLog = $this->processAiku($paymentGatewayLog);

        return ProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);
    }

    public function processAiku(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $payload = $paymentGatewayLog->payload;

        $operation = Arr::get($payload, 'data.metadata.operation');

        $dataToUpdate = [
            'operation' => $operation,
        ];

        if ($operation == 'top_up') {
            $topUpPaymentApiPoint = TopUpPaymentApiPoint::where('ulid', Arr::get($payload, 'data.metadata.api_point_ulid'))->first();
            if ($topUpPaymentApiPoint) {
                $dataToUpdate['api_point_model_type'] = 'TopUpPaymentApiPoint';
                $dataToUpdate['api_point_model_id']   = $topUpPaymentApiPoint->id;
                $dataToUpdate['organisation_id']      = $topUpPaymentApiPoint->organisation_id;
                $dataToUpdate['shop_id']              = $topUpPaymentApiPoint->customer?->shop_id;
                $dataToUpdate['customer_id']          = $topUpPaymentApiPoint->customer_id;
            } else {
                $dataToUpdate['status'] = PaymentGatewayLogStatusEnum::FAIL;
            }
        } elseif ($operation == 'order') {
            $orderPaymentApiPoint = OrderPaymentApiPoint::find(Arr::get($payload, 'data.metadata.api_point_id'));
            if ($orderPaymentApiPoint) {
                $dataToUpdate['api_point_model_type'] = 'OrderPaymentApiPoint';
                $dataToUpdate['api_point_model_id']   = $orderPaymentApiPoint->id;
                $dataToUpdate['organisation_id']      = $orderPaymentApiPoint->organisation_id;
                $dataToUpdate['shop_id']              = $orderPaymentApiPoint->order?->shop_id;
                $dataToUpdate['customer_id']          = $orderPaymentApiPoint->order?->customer_id;
                $dataToUpdate['order_id']             = $orderPaymentApiPoint->order_id;
            } else {
                $dataToUpdate['status'] = PaymentGatewayLogStatusEnum::FAIL;
            }
        } elseif ($operation == 'mit') {
            $order = Order::where('id', Arr::get($payload, 'data.metadata.order_id'))->first();
            if ($order) {
                $dataToUpdate['organisation_id'] = $order->organisation_id;
                $dataToUpdate['shop_id']         = $order->customer->shop_id;
                $dataToUpdate['customer_id']     = $order->customer_id;
                $dataToUpdate['order_id']        = $order->id;
            } else {
                $dataToUpdate['status'] = PaymentGatewayLogStatusEnum::FAIL;
            }
        }

        $dataToUpdate['state'] = PaymentGatewayLogStateEnum::PREPROCESSED;
        $paymentGatewayLog->update($dataToUpdate);

        return $paymentGatewayLog;
    }

    public function processAurora(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $dataToUpdate = [
            'origin'      => 'aurora',
            'environment' => 'production',

        ];
        $paymentGatewayLog->update($dataToUpdate);

        return $paymentGatewayLog;
    }

    public function getCommandSignature(): string
    {
        return 'payment_gateway_log:process {payment_gateway_log}';
    }

    public function asCommand(Command $command): int
    {
        $paymentGatewayLog = PaymentGatewayLog::find($command->argument('payment_gateway_log'));
        if (!$paymentGatewayLog) {
            $command->error('Payment gateway log not found');

            return 1;
        }

        $this->handle($paymentGatewayLog);

        return 0;
    }

}
