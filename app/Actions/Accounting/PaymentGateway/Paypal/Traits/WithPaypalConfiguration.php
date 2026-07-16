<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Traits;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Support\Arr;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

trait WithPaypalConfiguration
{
    /**
     * @throws \Throwable
     */
    public function getPaypalConfiguration($clientId, $clientSecret, ?string $currencyCode = null): PayPalClient
    {
        $mode = app()->isProduction() ? 'live' : 'sandbox';

        $provider = new PayPalClient();

        $config = [
            'mode' => $mode,
            $mode  => [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'app_id'        => '',
            ],

            'payment_action' => 'Sale',
            'currency'       => $currencyCode ?? 'USD',
            'notify_url'     => '',
            'locale'         => 'en_US',
            'validate_ssl'   => true,
        ];

        $provider->setApiCredentials($config);
        $provider->getAccessToken();

        return $provider;
    }

    /**
     * @throws \Throwable
     */
    public function getPaypalProvider(PaymentAccount $paymentAccount, ?string $currencyCode = null): PayPalClient
    {
        return $this->getPaypalConfiguration(
            Arr::get($paymentAccount->data, 'paypal_client_id'),
            Arr::get($paymentAccount->data, 'paypal_client_secret'),
            $currencyCode
        );
    }
}
