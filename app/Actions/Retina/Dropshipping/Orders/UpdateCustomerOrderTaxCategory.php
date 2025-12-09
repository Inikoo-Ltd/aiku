<?php

/*
 * author Louis Perez
 * created on 18-11-2025-16h-46m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Enums\Helpers\TaxNumber\TaxNumberTypeEnum;
use App\Models\Ordering\Order;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Helpers\TaxNumber\ValidateEuropeanTaxNumber;
use App\Actions\Helpers\TaxNumber\ValidateGBTaxNumber;
use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Http\Resources\Api\OrderResource;
use Sentry;

class UpdateCustomerOrderTaxCategory extends RetinaAction
{

    public function handle(Order $order): Order
    {
        $taxCategory = null;
        try {
            $taxNumber = $order->customer->taxNumber;
            // Check Tax Number
            if ($taxNumber?->type == TaxNumberTypeEnum::EU_VAT) {
                $taxNumber = ValidateEuropeanTaxNumber::run($taxNumber);
            } elseif ($taxNumber?->type == TaxNumberTypeEnum::GB_VAT) {
                $taxNumber = ValidateGBTaxNumber::run($taxNumber);
            }
            // Fetch tax category id
            $taxCategory = GetTaxCategory::run(
                country: $order->organisation->country,
                taxNumber: $taxNumber,
                billingAddress: $order->billingAddress,
                deliveryAddress: $order->deliveryAddress,
                isRe: $order->customer->is_re,
            )->id;
        } catch (Exception $e) {
            Sentry::captureException($e);
        } finally {
            if ($taxCategory) {
                $order->tax_category_id = $taxCategory;
                $order->save();
            }
        }

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->platform = $customerSalesChannel->platform;
        $this->initialisation($request);

        return $this->handle($order);
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }
}
