<?php

namespace App\Actions\Accounting\PaymentGateway\Btree;

use App\Actions\Accounting\PaymentGateway\Btree\Traits\WithBtreeConfiguration;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ChargeBtreePaymentMethod
{
    use AsAction;
    use WithBtreeConfiguration;

    /**
     * @return array{id?: string, legacyId?: string, status?: string, amount?: array{value: string, currencyCode: string}}
     */
    public function handle(PaymentAccount $paymentAccount, string $paymentMethodNonce, string $amount, array $transactionData = []): array
    {
        $response = $this->btreeGraphql(
            $paymentAccount,
            'mutation ChargePaymentMethod($input: ChargePaymentMethodInput!) {
                chargePaymentMethod(input: $input) {
                    transaction {
                        id
                        legacyId
                        status
                        amount {
                            value
                            currencyCode
                        }
                    }
                }
            }',
            [
                'input' => [
                    'paymentMethodId' => $paymentMethodNonce,
                    'transaction'     => array_merge(['amount' => $amount], $transactionData),
                ]
            ]
        );

        return Arr::get($response, 'chargePaymentMethod.transaction', []);
    }
}
