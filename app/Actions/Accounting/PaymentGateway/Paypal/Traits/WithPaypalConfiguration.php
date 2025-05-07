<?php

namespace App\Actions\Accounting\PaymentGateway\Paypal\Traits;

use Srmklive\PayPal\Services\PayPal as PayPalClient;

trait WithPaypalConfiguration
{
    public function getPaypalConfiguration($clientId, $clientSecret): PayPalClient
    {
        $provider = new PayPalClient();

        $config = [
            'mode'    => 'sandbox',
            'sandbox' => [
                'client_id'         => $clientId,
                'client_secret'     => $clientSecret
            ],

            'payment_action' => 'Sale',
            'currency'       => 'USD',
            'notify_url'     => 'https://your-site.com/paypal/notify',
            'locale'         => 'en_US',
            'validate_ssl'   => true,
        ];

        $provider->setApiCredentials($config);
        $provider->getAccessToken();

        return $provider;
    }
}
