<?php

namespace App\Actions\Accounting\PaymentGateway\Btree;

use App\Actions\Accounting\PaymentGateway\Btree\Traits\WithBtreeConfiguration;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateBtreeClientToken
{
    use AsAction;
    use WithBtreeConfiguration;

    public function handle(PaymentAccount $paymentAccount, array $modelData = []): string
    {
        $response = $this->btreeGraphql(
            $paymentAccount,
            'mutation CreateClientToken($input: CreateClientTokenInput) {
                createClientToken(input: $input) {
                    clientToken
                }
            }',
            [
                'input' => $modelData
            ]
        );

        return Arr::get($response, 'createClientToken.clientToken', '');
    }
}
