<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 10:48:35 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\CheckoutCom;

use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStateEnum;
use App\Enums\Accounting\PaymentGatewayLog\PaymentGatewayLogStatusEnum;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\PaymentGatewayLog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class PreProcessCheckoutComPaymentGatewayLog
{
    use asAction;

    public function handle(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $payload = $paymentGatewayLog->payload;

        $gatewayId = Arr::get($payload, 'id');


        $paymentGatewayLog->update([
            'state'        => PaymentGatewayLogStateEnum::PREPROCESSING,
            'gateway_id'   => $gatewayId,
            'type'         => Arr::get($payload, 'type'),
            'gateway_date' => Arr::get($payload, 'data.processed_on'),
        ]);

        if ($duplicatedPaymentGatewayLog = PaymentGatewayLog::where('id', '!=', $paymentGatewayLog->id)
            ->where('gateway_id', $gatewayId)
            ->orderBy('id')
            ->first()) {
            $paymentGatewayLog->update([
                'status' => PaymentGatewayLogStatusEnum::DUPLICATED,
                'state'  => PaymentGatewayLogStateEnum::PROCESSED
            ]);

            ProcessCheckoutComPaymentGatewayLog::run($duplicatedPaymentGatewayLog);

            return $paymentGatewayLog;
        }


        if (!Arr::get($payload, 'data.metadata.origin') == 'aiku') {
            return $this->processAurora($paymentGatewayLog);
        }

        $environment = Arr::get($payload, 'data.metadata.environment');

        if (app()->isProduction() && $environment != 'production') {
            return $paymentGatewayLog;
        }

        $paymentGatewayLog = $this->processAiku($paymentGatewayLog);



        return ProcessCheckoutComPaymentGatewayLog::run($paymentGatewayLog);
    }

    public function processAiku(PaymentGatewayLog $paymentGatewayLog): PaymentGatewayLog
    {
        $payload = $paymentGatewayLog->payload;

        $operation = Arr::get($payload, 'data.metadata.operation');


        $dataToUpdate = [
            'origin'      => 'aiku',
            'environment' => Arr::get($payload, 'data.metadata.environment'),
            'operation'   => $operation,
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

        $this->handle($paymentGatewayLog);

        return 0;
    }

}
