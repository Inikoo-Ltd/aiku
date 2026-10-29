<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Ordering\Order\SaveOrderModification;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\Ordering\Order\UpdateOrderDeliveryAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\User;
use App\Rules\ValidAddress;
use App\Models\Chat\MetaChatSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Description('Changes an order of the customer of a chat that has not been dispatched: delivery_address replaces where it is delivered (and on its deliveries not yet packed with a label), notes adds text to the order notes (customer_notes, public_notes, shipping_notes and private_warehouse_note also reach the warehouse on the delivery; internal_notes stay in the office), products sets the quantity of product codes (0 removes the product, a code not on the order adds it), which is allowed while the order is submitted or in the warehouse and packing has not started. Pass any of the three together. Call customer-service-chat-tool with the order first. Without confirm=true nothing happens and a preview comes back: show it to the user and change only after they say yes in their own words. Only for users enrolled to serve customers through their assistant.')]
class CustomerServiceOrderTool extends AikuCustomerServiceTool
{
    use WithMcpChangeLog;

    private const array NOTE_FIELDS = ['customer_notes', 'public_notes', 'internal_notes', 'shipping_notes', 'private_warehouse_note'];

    private const array ADDRESS_FIELDS = ['address_line_1', 'address_line_2', 'sorting_code', 'postal_code', 'dependent_locality', 'locality', 'administrative_area'];

    public function handle(Request $request): Response
    {
        $request->validate([
            'chat_id'                       => ['required', 'string', 'max:40'],
            'order'                         => ['required', 'string', 'max:64'],
            'delivery_address'              => ['sometimes', 'array'],
            'delivery_address.country_code' => ['required_with:delivery_address', 'string', 'size:2'],
            'update_customer_address'       => ['sometimes', 'boolean'],
            'notes'                         => ['sometimes', 'array'],
            'notes.*'                       => ['string', 'max:2000'],
            'products'                      => ['sometimes', 'array', 'max:50'],
            'products.*.code'               => ['required', 'string'],
            'products.*.quantity'           => ['required', 'numeric', 'min:0'],
            'confirm'                       => ['sometimes', 'boolean'],
            'request_text'                  => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
        ]);

        $denied = $this->notEnrolled($request);
        if ($denied) {
            return $denied;
        }

        [$chatSession, $error] = $this->chatSessionFor($request, toActOnIt: true);
        if ($error) {
            return $error;
        }

        if ($chatSession instanceof MetaChatSession) {
            return $this->notOnWhatsapp('Changing an order');
        }

        [$order, $error] = $this->orderOf($chatSession, (string) $request->string('order'));
        if ($error) {
            return $error;
        }

        $denied = $this->cannotOnShop($request, $chatSession, ShopPermissionsEnum::ORDERS_EDIT, 'edit orders');
        if ($denied) {
            return $denied;
        }

        if (in_array($order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED, OrderStateEnum::CANCELLED])) {
            return Response::error("{$order->reference} is {$order->state->value} and can no longer be changed. Nothing was changed.");
        }

        try {
            $address      = $request->has('delivery_address') ? $this->address($request->get('delivery_address')) : null;
            $notes        = $this->notes($order, $request->get('notes', []));
            $modification = $this->modification($order, $request->get('products', []));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        }

        if (!$address && !$notes && !$modification['preview']) {
            return Response::error('Nothing to change: pass delivery_address, notes or products.');
        }

        if (!$request->boolean('confirm')) {
            return $this->askToConfirm(array_filter([
                'order'            => $order->reference,
                'customer'         => $order->customer->name,
                'delivery_address' => $address ? [
                    'from' => $order->deliveryAddress?->only([...self::ADDRESS_FIELDS, 'country_code']),
                    'to'   => [...Arr::except($address, 'country_id'), 'country_code' => strtoupper((string) data_get($request->get('delivery_address'), 'country_code'))],
                    'also_saved_on_the_customer' => $request->boolean('update_customer_address'),
                ] : null,
                'notes'            => $notes,
                'products'         => $modification['preview'],
            ]));
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->recordChange(
                $request,
                McpChangeTypeEnum::CUSTOMER_SERVICE,
                collect([$address ? 'delivery address' : null, $notes ? 'notes' : null, $modification['preview'] ? 'products' : null])->filter()->implode(', ').' of '.$order->reference,
                ['chat_session_id' => $chatSession->id, 'order_id' => $order->id],
                fn () => DB::transaction(function () use ($order, $address, $notes, $modification, $request, $user) {
                    if ($address) {
                        UpdateOrderDeliveryAddress::make()->action($order, ['address' => $address, 'update_parent' => $request->boolean('update_customer_address')]);
                    }

                    if ($notes) {
                        UpdateOrder::make()->action($order->refresh(), $notes);
                    }

                    if ($modification['preview']) {
                        SaveOrderModification::make()->handle($order->refresh(), $modification['data'], $user);
                    }
                }),
                ['action' => 'order', 'products' => $modification['preview']]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        } catch (HttpException $exception) {
            return Response::error($exception->getMessage().' Nothing was changed.');
        }

        $order->refresh();

        return Response::json([
            'done'          => array_keys(array_filter(['delivery_address' => $address, 'notes' => $notes, 'products' => $modification['preview']])),
            'order'         => $order->reference,
            'state'         => $order->state->value,
            'total'         => (float) $order->total_amount,
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function address(array $given): array
    {
        $address = [
            ...array_filter(Arr::only($given, self::ADDRESS_FIELDS), fn ($value) => $value !== null && $value !== ''),
            'country_id' => DB::table('countries')->whereRaw('lower(code) = ?', [strtolower((string) Arr::get($given, 'country_code'))])->value('id'),
        ];

        validator(['delivery_address' => $address], ['delivery_address' => ['required', new ValidAddress(requireFullAddress: true)]])->validate();

        return $address;
    }

    /**
     * Notes are added to what is already written, never put in its place: the warehouse and the
     * office may both have left something there.
     *
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function notes(Order $order, array $given): array
    {
        $unknown = array_diff(array_keys($given), self::NOTE_FIELDS);

        if ($unknown) {
            throw ValidationException::withMessages(['notes' => 'Unknown note: '.implode(', ', $unknown).'. Use '.implode(', ', self::NOTE_FIELDS).'.']);
        }

        return collect($given)
            ->map(fn (string $text) => trim($text))
            ->filter()
            ->map(fn (string $text, string $field) => trim($order->{$field}."\n".$text))
            ->all();
    }

    /**
     * @return array{preview: array<int, string>, data: array<string, array<int, array<string, float>>>}
     *
     * @throws ValidationException
     */
    private function modification(Order $order, array $given): array
    {
        if (!$given) {
            return ['preview' => [], 'data' => []];
        }

        if (!SaveOrderModification::acceptsNewProducts($order)) {
            throw ValidationException::withMessages(['products' => "The products of {$order->reference} can no longer be changed: it is {$order->state->value}, and products change only while an order is submitted or in the warehouse before packing, on orders taken in Aiku."]);
        }

        $transactions = $order->transactions()->where('model_type', 'Product')->where('is_gift', false)->with('asset:id,code')->get()
            ->keyBy(fn (Transaction $transaction) => strtolower((string) $transaction->asset?->code));
        $preview      = [];
        $data         = [];

        foreach ($given as $line) {
            $code        = trim($line['code']);
            $quantity    = (float) $line['quantity'];
            $transaction = $transactions->get(strtolower($code));

            if ($transaction) {
                if ((float) $transaction->quantity_ordered !== $quantity) {
                    $data['transactions'][$transaction->id] = ['newQty' => $quantity];
                    $preview[]                              = $transaction->asset->code.': '.(float) $transaction->quantity_ordered.' to '.($quantity > 0 ? $quantity : 'removed');
                }

                continue;
            }

            $product = Product::where('shop_id', $order->shop_id)->whereRaw('lower(code) = ?', [strtolower($code)])->first();

            if (!$product || $quantity <= 0) {
                throw ValidationException::withMessages(['products' => $product ? "{$code} is not on {$order->reference}, so there is nothing to remove." : "{$code} is not a product of {$order->shop->name}."]);
            }

            $data['products'][$product->id] = ['quantity_ordered' => $quantity];
            $preview[]                      = $product->code.': added, '.$quantity;
        }

        return ['preview' => $preview, 'data' => $data];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'chat_id'                 => $schema->string()->description('Chat ID copied from the Overview panel of the chat')->required(),
            'order'                   => $schema->string()->description('Reference of the customer\'s order to change')->required(),
            'delivery_address'        => $schema->object([
                'address_line_1'      => $schema->string(),
                'address_line_2'      => $schema->string(),
                'locality'            => $schema->string()->description('Town or city'),
                'administrative_area' => $schema->string()->description('County, province or state, where the country uses one'),
                'postal_code'         => $schema->string(),
                'country_code'        => $schema->string()->description('Two letter country code, e.g. GB')->required(),
            ])->description('The whole new delivery address, not only the part that changes'),
            'update_customer_address' => $schema->boolean()->description('Also save it as the customer\'s delivery address for future orders; false by default'),
            'notes'                   => $schema->object()->description('Text to add, per note: customer_notes, public_notes, shipping_notes, private_warehouse_note (these reach the warehouse) or internal_notes'),
            'products'                => $schema->array()->items($schema->object([
                'code'     => $schema->string()->description('Product code')->required(),
                'quantity' => $schema->number()->description('New quantity on the order; 0 removes the product')->required(),
            ]))->description('Products to add, remove or requantify'),
            'confirm'                 => $schema->boolean()->description('true only after the user has seen the preview and said yes'),
            'request_text'            => $schema->string()->description('The user\'s request, verbatim, kept in the AI changes log; required with confirm=true'),
        ];
    }
}
