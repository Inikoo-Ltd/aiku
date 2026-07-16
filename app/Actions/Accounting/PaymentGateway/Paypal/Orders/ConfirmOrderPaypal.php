<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Orders;

use App\Actions\Accounting\PaymentGateway\Paypal\Traits\WithPaypalConfiguration;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmOrderPaypal
{
    use AsAction;
    use WithPaypalConfiguration;

    public string $commandSignature   = 'paypal:confirm {paymentAccount} {orderId}';
    public string $commandDescription = 'Confirm checkout detail using paypal';

    /**
     * @throws \Throwable
     */
    public function handle(PaymentAccount $paymentAccount, string $orderId, array $modelData = []): array
    {
        $provider = $this->getPaypalProvider($paymentAccount);

        return $provider->confirmOrder($orderId, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $paymentAccount = PaymentAccount::where('slug', $command->argument('paymentAccount'))->firstOrFail();

        $command->line(json_encode($this->handle($paymentAccount, $command->argument('orderId')), JSON_PRETTY_PRINT));

        return 0;
    }
}
