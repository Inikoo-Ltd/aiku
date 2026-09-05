<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 10:05:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting;

use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use Checkout\CheckoutApiException;
use Checkout\Customers\CustomerRequest;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Payments\BillingInformation;
use Checkout\Payments\PaymentsQueryFilter;
use Checkout\Payments\Sessions\PaymentSessionsClient;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use Sentry;

trait WithCheckoutCom
{
    public const array CHECKOUT_COM_PENDING_STATUSES = ['Pending', 'Retry Scheduled'];
    public const array CHECKOUT_COM_FAILURE_STATUSES = ['Voided', 'Declined', 'Cancelled', 'Canceled', 'Expired'];
    public const array CHECKOUT_COM_CAPTURED_STATUSES = ['Captured', 'Paid'];
    public const array CHECKOUT_COM_CUSTOMER_PHONE_CURRENCIES = ['SEK'];

    public function getCheckoutApi($publicKey, $secretKey): ?\Checkout\CheckoutApi
    {
        $checkoutApi = null;
        try {
            $checkoutApi = CheckoutSdk::builder()->staticKeys()
                ->publicKey($publicKey)
                ->secretKey($secretKey)
                ->environment(app()->environment('production') ? Environment::production() : Environment::sandbox())
                ->build();
        } catch (\Exception $e) {
            Sentry::captureException($e);
        }

        return $checkoutApi;
    }


    private function setBillingInformation(PaymentSessionsRequest $paymentSessionRequest, ?Address $billingAddress): PaymentSessionsRequest
    {
        if (!$billingAddress?->country) {
            return $paymentSessionRequest;
        }

        $address                = new \Checkout\Common\Address();
        $address->address_line1 = $billingAddress->address_line_1;
        $address->address_line2 = $billingAddress->address_line_2;

        $address->city    = $billingAddress->locality;
        $address->state   = $billingAddress->administrative_area;
        $address->zip     = $billingAddress->postal_code;
        $address->country = $billingAddress->country->code;

        $paymentSessionRequest->billing          = new BillingInformation();
        $paymentSessionRequest->billing->address = $address;

        return $paymentSessionRequest;
    }

    public function getCustomerPhone(?string $phone, ?string $billingCountryPhoneCode): ?\Checkout\Common\Phone
    {
        $phone = trim((string) $phone);
        preg_match('/\d+/', (string) $billingCountryPhoneCode, $phoneCodeDigits);
        $billingPhoneCode = $phoneCodeDigits[0] ?? '';
        if ($phone === '' || $billingPhoneCode === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', str_replace('(0)', '', $phone));

        $carriesCountryCode = str_starts_with($phone, '+')
            || str_starts_with($digits, '00')
            || (!str_starts_with($digits, '0') && str_starts_with($digits, $billingPhoneCode) && strlen($digits) >= 10);

        $international = $carriesCountryCode ? ltrim($digits, '0') : $billingPhoneCode.ltrim($digits, '0');

        if (!str_starts_with($international, $billingPhoneCode)) {
            if (strlen($international) < 8 || strlen($international) > 24) {
                return null;
            }
            $checkoutPhone         = new \Checkout\Common\Phone();
            $checkoutPhone->number = '+'.$international;

            return $checkoutPhone;
        }

        $number = ltrim(substr($international, strlen($billingPhoneCode)), '0');
        if (strlen($number) < 6 || strlen($number) > 25) {
            return null;
        }

        $checkoutPhone               = new \Checkout\Common\Phone();
        $checkoutPhone->country_code = '+'.$billingPhoneCode;
        $checkoutPhone->number       = $number;

        return $checkoutPhone;
    }

    private function setCustomerPhone(PaymentSessionsRequest $paymentSessionRequest, string $currencyCode, Customer $customer, ?string $billingCountryPhoneCode): PaymentSessionsRequest
    {
        if (!in_array($currencyCode, self::CHECKOUT_COM_CUSTOMER_PHONE_CURRENCIES)) {
            return $paymentSessionRequest;
        }

        $customerPhone = $this->getCustomerPhone($customer->phone, $billingCountryPhoneCode);
        if (!$customerPhone) {
            return $paymentSessionRequest;
        }

        if (!$paymentSessionRequest->customer) {
            $paymentSessionRequest->customer       = new CustomerRequest();
            $paymentSessionRequest->customer->name = $customer->name;
            if ($customer->email) {
                $paymentSessionRequest->customer->email = $customer->email;
            }
        }
        $paymentSessionRequest->customer->phone = $customerPhone;

        return $paymentSessionRequest;
    }

    private function createPaymentSession(PaymentSessionsClient $paymentSessionClient, PaymentSessionsRequest $paymentSessionRequest): array
    {
        try {
            return $paymentSessionClient->createPaymentSessions($paymentSessionRequest);
        } catch (\Exception $e) {
            Sentry::captureException($e);
            if (!$this->isCustomerPhoneRejected($e, $paymentSessionRequest)) {
                return ['error' => $e->getMessage()];
            }
        }

        $paymentSessionRequest->customer->phone = null;
        try {
            return $paymentSessionClient->createPaymentSessions($paymentSessionRequest);
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return ['error' => $e->getMessage()];
        }
    }

    private function isCustomerPhoneRejected(\Exception $e, PaymentSessionsRequest $paymentSessionRequest): bool
    {
        if (empty($paymentSessionRequest->customer?->phone) || !$e instanceof CheckoutApiException) {
            return false;
        }

        return isset($e->http_metadata) && $e->http_metadata->getStatusCode() == 422;
    }

    public function getCheckOutPaymentsByReference(PaymentAccountShop $paymentAccountShop, string $reference): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();

        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);
        if (!$checkoutApi) {
            return [
                'error'            => true,
                'http_status_code' => null,
            ];
        }

        try {
            $queryFilter            = new PaymentsQueryFilter();
            $queryFilter->reference = $reference;

            return $checkoutApi->getPaymentsClient()->getPaymentsList($queryFilter);
        } catch (CheckoutApiException $e) {
            $httpStatusCode = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
            if ($httpStatusCode == 404) {
                return ['data' => []];
            }

            \Sentry\captureException($e);

            return [
                'error'             => true,
                'http_status_code'  => $httpStatusCode,
            ];
        }
    }

    private function getCheckOutPayment(PaymentAccountShop $paymentAccountShop, string $paymentID): array
    {
        list($publicKey, $secretKey) = $paymentAccountShop->getCredentials();


        $checkoutApi = $this->getCheckoutApi($publicKey, $secretKey);
        if (!$checkoutApi) {
            return [
                'error'            => true,
                'message'          => 'checkout.com credentials rejected',
                'http_status_code' => null,
            ];
        }

        try {
            return $checkoutApi->getPaymentsClient()->getPaymentDetails($paymentID);
        } catch (CheckoutApiException $e) {
            \Sentry\captureException($e);
            $error_details    = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;

            return [
                'error' => true,
                'message' => $error_details,
                'http_status_code' => $http_status_code
            ];
        }

    }



    protected function getFailureTitle($status): string
    {
        $title = __('Error');
        if ($status == 'Declined') {
            $title = __('Declined');
        } elseif ($status == 'Expired') {
            $title = __('Expired');
        } elseif ($status == 'Canceled') {
            $title = __('Canceled');
        }

        return $title;
    }

    protected function getFailureMessage($status): string
    {
        $message = __('There was an error processing your card. Please try again or use a different payment method.');

        if ($status == 'Declined') {
            $message = __('Your card was declined by the issuing bank. Please try another payment method or contact your bank for more information.');
        } elseif ($status == 'Expired') {
            $message = __('Your payment session has expired. Please try the transaction again.');
        } elseif ($status == 'Canceled') {
            $message = __('This payment was canceled. Please try again when you are ready to complete your payment.');
        } elseif ($status == 'Failed') {
            $message = __('The payment processing failed. Please verify your card details and try again.');
        }

        return $message;
    }

}
