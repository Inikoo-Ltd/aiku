<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ShoppingAssistant\UI;

use App\Actions\CRM\ShoppingAssistant\GetShoppingAssistantReport;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Http\Resources\Web\ShoppingAssistantConversationsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The transcript of one AI assistant conversation, with what each answer showed and what came of it.
 * Served only inside the scope of the route: the website, or the group on WebOps.
 */
class ShowShoppingAssistantConversation extends OrgAction
{
    use WithWebAuthorisation {
        authorize as authorizeWebsiteRoute;
    }

    private ?Website $website = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->website ? $this->authorizeWebsiteRoute($request) : $this->canViewGroupShoppingAssistant($request->user());
    }

    public function asController(ShoppingAssistantConversation $conversation, ActionRequest $request): ShoppingAssistantConversation
    {
        $this->initialisationFromGroup(group(), $request);
        abort_unless($conversation->group_id === group()->id, 404);

        return $this->handle($conversation);
    }

    public function inWebsite(Organisation $organisation, Shop $shop, Website $website, ShoppingAssistantConversation $conversation, ActionRequest $request): ShoppingAssistantConversation
    {
        abort_unless($website->shop_id === $shop->id, 404);
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);
        abort_unless($conversation->website_id === $website->id, 404);

        return $this->handle($conversation);
    }

    public function handle(ShoppingAssistantConversation $conversation): ShoppingAssistantConversation
    {
        abort_if($conversation->entry_point === ShoppingAssistantEntryPointEnum::COMMAND, 404);

        return $conversation;
    }

    public function htmlResponse(ShoppingAssistantConversation $conversation, ActionRequest $request): Response
    {
        $report       = GetShoppingAssistantReport::make();
        $performance  = ShowShoppingAssistantPerformance::make();
        [$currencyCode, $inShopCurrency] = $performance->currency($this->website);
        $usdRate      = $report->usdRate($currencyCode);
        $amountColumn = $inShopCurrency ? 'net_amount' : 'grp_net_amount';
        $customer     = $conversation->customer;
        $title        = __('AI Assistant');

        $productOutcomes = collect($report->selectWithAttribution(
            DB::table('shopping_assistant_conversations')
                ->where('shopping_assistant_conversations.id', $conversation->id)
                ->select(['shopping_assistant_conversations.id', 'shopping_assistant_conversations.customer_id']),
            $amountColumn,
            'select matches.product_id, coalesce((select sum(lines.amount) from lines where lines.product_id = matches.product_id), 0) as revenue from matches group by matches.product_id'
        ))->keyBy('product_id');

        $events = $conversation->events()->with('product:id,code,name,slug')->orderBy('position')->orderBy('id')->get();
        $done   = fn (ShoppingAssistantEvent $shown, ShoppingAssistantEventTypeEnum $type) => $events->contains(
            fn (ShoppingAssistantEvent $event) => $event->type === $type && $event->product_id === $shown->product_id && $event->shopping_assistant_message_id === $shown->shopping_assistant_message_id
        );

        $messages = $conversation->messages()->orderBy('id')->get()->map(function (ShoppingAssistantMessage $message) use ($events, $done, $productOutcomes, $conversation, $usdRate) {
            $base = [
                'id'         => $message->id,
                'role'       => $message->role->value,
                'body'       => (string) $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ];

            if ($message->role === ShoppingAssistantMessageRoleEnum::CUSTOMER) {
                return $base;
            }

            return array_merge($base, [
                'follow_ups' => $message->follow_ups ?? [],
                'flags'      => $message->flags ?? [],
                'latency_ms' => $message->latency_ms,
                'cost'       => $message->cost === null ? null : (float) $message->cost * ($usdRate ?? 1),
                'model'      => $message->model,
                'products'   => $events
                    ->filter(fn (ShoppingAssistantEvent $event) => $event->type === ShoppingAssistantEventTypeEnum::SHOWN && $event->shopping_assistant_message_id === $message->id)
                    ->values()
                    ->map(fn (ShoppingAssistantEvent $shown) => [
                        'id'      => $shown->product_id,
                        'code'    => $shown->product?->code,
                        'name'    => $shown->product?->name,
                        'group'   => $shown->group,
                        'reason'  => $shown->reason,
                        'clicked' => $done($shown, ShoppingAssistantEventTypeEnum::CLICKED),
                        'added'   => $done($shown, ShoppingAssistantEventTypeEnum::ADDED),
                        'ordered' => $productOutcomes->has($shown->product_id),
                        'revenue' => (float) ($productOutcomes[$shown->product_id]->revenue ?? 0),
                        'route'   => $shown->product ? [
                            'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                            'parameters' => [$conversation->organisation->slug, $conversation->shop->slug, $shown->product->slug],
                        ] : null,
                    ])->all(),
            ]);
        })->all();

        return Inertia::render(
            'Org/Web/ShoppingAssistantConversation',
            [
                'title'            => $title,
                'breadcrumbs'      => $this->getBreadcrumbs($conversation, $request->route()->originalParameters()),
                'pageHead'         => [
                    'title' => $customer?->name ?? $title,
                    'model' => $title,
                    'icon'  => ['icon' => ['fal', 'fa-robot'], 'title' => $title],
                ],
                'currency'         => $currencyCode,
                'cost_currency'    => $usdRate === null ? 'USD' : $currencyCode,
                'attribution_days' => $report->attributionDays(),
                'conversation'     => [
                    'ulid'              => $conversation->ulid,
                    'created_at'        => $conversation->created_at?->toIso8601String(),
                    'last_message_at'   => $conversation->last_message_at?->toIso8601String(),
                    'entry_point_label' => ShowShoppingAssistantPerformance::entryPointLabel($conversation->entry_point->value),
                    'status_label'      => ShoppingAssistantConversationsResource::statusLabel($conversation->status),
                    'language_code'     => $conversation->language_code,
                    'website'           => $conversation->website_id ? Website::whereKey($conversation->website_id)->value('domain') : null,
                    'customer'          => [
                        'name'            => $customer?->name,
                        'reference'       => $customer?->reference,
                        'shop_type_label' => $customer?->shop_type ? (CustomerShopTypeEnum::labels()[$customer->shop_type->value] ?? null) : null,
                        'country'         => $customer?->location[0] ?? null,
                        'route'           => $customer ? [
                            'name'       => 'grp.org.shops.show.crm.customers.show',
                            'parameters' => [$conversation->organisation->slug, $conversation->shop->slug, $customer->slug],
                        ] : null,
                    ],
                    'messages_count'    => (int) $conversation->messages_count,
                    'model_calls'       => (int) $conversation->model_calls,
                    'prompt_tokens'     => (int) $conversation->prompt_tokens,
                    'completion_tokens' => (int) $conversation->completion_tokens,
                    'cost'              => (float) $conversation->cost * ($usdRate ?? 1),
                    'model'             => $conversation->model,
                ],
                'messages'         => $messages,
            ]
        );
    }

    public function getBreadcrumbs(ShoppingAssistantConversation $conversation, array $routeParameters): array
    {
        $performance = ShowShoppingAssistantPerformance::make();
        $listParameters = collect($routeParameters)->except('conversation')->all();

        if ($this->website) {
            $performance->forWebsite($this->website);
        }

        return [
            ...$performance->getBreadcrumbs($listParameters),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => $this->website ? 'grp.org.shops.show.web.analytics.shopping_assistant.conversations.show' : 'grp.websites.shopping_assistant.conversations.show',
                        'parameters' => $routeParameters,
                    ],
                    'label' => $conversation->customer?->name ?? $conversation->ulid,
                ],
            ],
        ];
    }
}
