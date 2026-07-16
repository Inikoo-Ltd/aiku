<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders\Webhooks;

use App\Actions\Accounting\Payment\UpdatePayment;
use App\Models\Accounting\Payment;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class WebhookOrderPaypal
{
    use AsAction;

    public function handle(array $objectData): array
    {
        $orderId = Arr::get($objectData, 'resource.id');

        if ($orderId) {
            $payment = Payment::where('data->paypal->order_id', $orderId)->first();

            if ($payment) {
                UpdatePayment::run($payment, [
                    'data' => [
                        'paypal' => [
                            'last_webhook' => [
                                'event_type' => Arr::get($objectData, 'event_type'),
                                'status'     => Arr::get($objectData, 'resource.status'),
                                'created_at' => Arr::get($objectData, 'create_time'),
                            ]
                        ]
                    ]
                ]);
            }
        }

        return $objectData;
    }

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request->all());
    }
}
