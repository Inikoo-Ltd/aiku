<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\ChatSession\PlanChatClaim;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Chat\MetaChatSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Description('Settles a claim (damaged, missing or wrong items) on a dispatched order of the customer of a chat, the same three ways as the Claim panel: refund_to_balance credits the customer\'s balance for the claimed items (percentage below 100 for a goodwill part refund), replacement creates a replacement delivery the warehouse sends now, next_order adds the items free to the customer\'s next order. items are product codes with the quantity claimed, taken from the claimable list of customer-service-chat-tool called with the order. Without confirm=true nothing happens and a preview with the amount comes back: show it to the user and settle only after they say yes in their own words. A credit or a replacement cannot be undone from the AI changes log. Only for users enrolled to serve customers through their assistant.')]
class CustomerServiceClaimTool extends AikuCustomerServiceTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'chat_id'          => ['required', 'string', 'max:40'],
            'order'            => ['required', 'string', 'max:64'],
            'resolution'       => ['required', 'in:'.implode(',', PlanChatClaim::RESOLUTIONS)],
            'items'            => ['required', 'array', 'min:1', 'max:80'],
            'items.*.code'     => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'reason'           => ['sometimes', 'nullable', Rule::enum(DeliveryNoteItemReplacementReasonEnum::class)],
            'percentage'       => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'note'             => ['sometimes', 'nullable', 'string', 'max:4000'],
            'confirm'          => ['sometimes', 'boolean'],
            'request_text'     => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
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
            return $this->notOnWhatsapp('Settling a claim');
        }

        [$order, $error] = $this->orderOf($chatSession, (string) $request->string('order'));
        if ($error) {
            return $error;
        }

        $resolution = $request->string('resolution')->toString();

        $denied = $this->cannotOnShop($request, $chatSession, PlanChatClaim::permission($resolution), $resolution === 'replacement' ? 'create replacements' : 'settle claims');
        if ($denied) {
            return $denied;
        }

        $plan = PlanChatClaim::run($chatSession, $order, $resolution, $request->get('items'), $request->get('reason'), $request->get('percentage') !== null ? (float) $request->get('percentage') : null);

        if ($plan['error']) {
            return Response::error($plan['error']);
        }

        ['claimed' => $claimed, 'amount' => $amount, 'reason' => $reason, 'percentage' => $percentage] = $plan;

        $customer = $order->customer;

        if (!$request->boolean('confirm')) {
            return $this->askToConfirm(array_filter([
                'resolution' => $resolution,
                'order'      => $order->reference,
                'customer'   => $customer->name,
                'reason'     => DeliveryNoteItemReplacementReasonEnum::from($reason)->label(),
                'items'      => collect($claimed)->map(fn (array $item) => $item['code'].' x'.$item['quantity'].' '.$item['name'])->all(),
                'credit'     => $resolution === 'refund_to_balance' ? 'about '.round($amount, 2).' '.$plan['currency'].' to the balance of '.$customer->name.' (now '.(float) $customer->balance.')' : null,
                'percentage' => $resolution === 'refund_to_balance' && $percentage < 100 ? $percentage : null,
                'note'       => $request->get('note'),
            ]));
        }

        try {
            $result = $this->recordChange(
                $request,
                McpChangeTypeEnum::CUSTOMER_SERVICE,
                str_replace('_', ' ', $resolution).' for '.collect($claimed)->map(fn (array $item) => $item['code'].' x'.$item['quantity'])->implode(', ').' of '.$order->reference,
                ['chat_session_id' => $chatSession->id, 'order_id' => $order->id],
                fn () => PlanChatClaim::make()->settle($order, $resolution, $claimed, $reason, $percentage, $request->get('note')),
                ['action' => $resolution, 'items' => $claimed, 'percentage' => $percentage]
            );
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was changed.');
        } catch (HttpException $exception) {
            return Response::error($exception->getMessage().' Nothing was changed.');
        }

        return Response::json([
            'done'          => $resolution,
            'order'         => $order->reference,
            'result'        => $result,
            'balance'       => (float) $customer->refresh()->balance,
            'currency'      => $plan['currency'],
            'change_log_id' => $this->mcpChange?->id,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'chat_id'      => $schema->string()->description('Chat ID copied from the Overview panel of the chat')->required(),
            'order'        => $schema->string()->description('Reference of the dispatched order the claim is about, e.g. GB589603')->required(),
            'resolution'   => $schema->string()->enum(PlanChatClaim::RESOLUTIONS)->description('How the claim is settled')->required(),
            'items'        => $schema->array()->items($schema->object([
                'code'     => $schema->string()->description('Product code as delivered, e.g. SelCH-03')->required(),
                'quantity' => $schema->number()->description('Quantity claimed, in the units the customer ordered')->required(),
            ]))->description('What is claimed')->required(),
            'reason'       => $schema->string()->enum(array_column(DeliveryNoteItemReplacementReasonEnum::cases(), 'value'))->description('Why; defaults to what the conversation suggests'),
            'percentage'   => $schema->number()->description('refund_to_balance only: part of the value to refund, 100 by default'),
            'note'         => $schema->string()->description('replacement: private note for the warehouse; next_order: private note on the items'),
            'confirm'      => $schema->boolean()->description('true only after the user has seen the preview and said yes'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim, kept in the AI changes log; required with confirm=true'),
        ];
    }
}
