<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\AI\AskJev;
use App\Actions\Helpers\Ticket\StoreTicket;
use App\Actions\Helpers\Ticket\StoreTicketComment;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketModuleEnum;
use App\Enums\Helpers\Ticket\TicketSourceChannelEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A MER ticket: a customer's problem Merlin sends straight to the engineers (the Archmages),
 * skipping customer services. Only a real problem only we can fix, never noise (Raul, 13 Nov 2026):
 * Merlin gives the reason, the customer already tried the fix in our guides, and an independent
 * check (AskJev) is at least MER_CONFIDENCE sure it is a defect in our system or data rather than
 * user error, a setting or their own platform. Anything else raises no ticket: Merlin hands the
 * customer to customer services in chat, who raise one themselves if it turns out to be real. The
 * ticket carries what an engineer asks first: the customer's words, Merlin's summary and reason,
 * the customer's sales channels with their counters and the conversation link. One open MER ticket
 * per customer: a new report while one is open becomes a comment on it. The customer sees it under
 * Tickets in their account when it has a web user.
 *
 * A question Merlin was about to pass to the gurus goes here instead when it reports something of
 * ours not working for this customer (an upload, a sync, stock, orders, photos, the connection) and
 * an independent check is at least FAULT_CONFIDENCE sure of it (fromQuestion()): a guru cannot
 * explain an integration fault, only engineers can (Raul, 14 Nov 2026, the Allegro photo of INI-260).
 * There the customer has not been offered a fix yet, so tried_fix is not asked for.
 */
class StoreShoppingAssistantEngineerTicket
{
    use AsAction;

    public const float MER_CONFIDENCE = 0.8;

    public const float FAULT_CONFIDENCE = 0.8;

    /**
     * @return array{status: 'created'|'already_reported'|'not_for_engineers', ticket: ?Ticket, confidence: ?float}
     */
    public function handle(ShoppingAssistantConversation $conversation, string $summary, string $details, TicketKindEnum $kind, string $whyOnlyUs, bool $triedFix): array
    {
        $customer = $conversation->customer;
        $webUser  = $conversation->web_user_id ? WebUser::find($conversation->web_user_id) : null;
        $words    = (string) $conversation->messages()->where('role', 'customer')->latest('id')->value('body');

        if ($open = $this->addToOpen($conversation, $details, $words, $webUser)) {
            return ['status' => 'already_reported', 'ticket' => $open, 'confidence' => null];
        }

        $isDropshipping = $customer->shop->type === ShopTypeEnum::DROPSHIPPING;
        $channels       = $isDropshipping ? $this->channels($conversation) : null;
        $confidence     = $triedFix && $whyOnlyUs !== '' ? $this->confidenceOnlyUs($summary, $details, $words, $whyOnlyUs, $channels) : null;

        if ($confidence === null || $confidence < self::MER_CONFIDENCE) {
            return ['status' => 'not_for_engineers', 'ticket' => null, 'confidence' => $confidence];
        }

        $ticket = $this->store($conversation, $summary, $this->description($conversation, $details, $words, $channels, $whyOnlyUs), $kind, $webUser, ['why_only_us' => $whyOnlyUs, 'confidence' => $confidence]);

        return ['status' => 'created', 'ticket' => $ticket, 'confidence' => $confidence];
    }

    /**
     * A question for the team that is really a fault report: a MER ticket instead of a guru task.
     *
     * @return array{status: 'created'|'already_reported'|'not_a_fault', ticket: ?Ticket, confidence: ?float}
     */
    public function fromQuestion(ShoppingAssistantConversation $conversation, string $questionInEnglish, string $customerWords): array
    {
        $customer       = $conversation->customer;
        $isDropshipping = $customer->shop->type === ShopTypeEnum::DROPSHIPPING;
        $channels       = $isDropshipping ? $this->channels($conversation) : null;
        $confidence     = $this->confidenceFault($questionInEnglish, $customerWords, $channels);

        if ($confidence === null || $confidence < self::FAULT_CONFIDENCE) {
            return ['status' => 'not_a_fault', 'ticket' => null, 'confidence' => $confidence];
        }

        $webUser = $conversation->web_user_id ? WebUser::find($conversation->web_user_id) : null;
        if ($open = $this->addToOpen($conversation, $questionInEnglish, $customerWords, $webUser)) {
            return ['status' => 'already_reported', 'ticket' => $open, 'confidence' => $confidence];
        }

        $parts = [
            'Raised by Merlin: the customer reported something of ours not working, and an independent check read it as a fault for engineers rather than a question for our team. No fix has been offered to the customer yet.',
            "What the customer reports:\n$questionInEnglish",
            "The customer's words:\n> ".str_replace("\n", "\n> ", trim($customerWords)),
        ];
        if ($channels !== null) {
            $parts[] = "Sales channels:\n".($channels ?: 'none');
        }
        $parts[] = 'Conversation: '.route('grp.org.shops.show.ai_salesman.conversations.show', [$customer->organisation->slug, $customer->shop->slug, $conversation->ulid]);

        $ticket = $this->store($conversation, $questionInEnglish, implode("\n\n", $parts), TicketKindEnum::BUG, $webUser, ['from_question' => true, 'confidence' => $confidence]);

        return ['status' => 'created', 'ticket' => $ticket, 'confidence' => $confidence];
    }

    /**
     * What the customer is told, with the reference. "Follow it under Tickets" only where customers see
     * their tickets: that section is local only until customer support launches (same guard as
     * GetRetinaDropshippingNavigation and NotifyTicketUsers::notifyCustomer; drop it with them).
     */
    public static function customerSentence(ShoppingAssistantConversation $conversation, Ticket $ticket, bool $alreadyReported): string
    {
        $locale    = $conversation->language_code;
        $canFollow = $conversation->web_user_id
            && $conversation->customer?->shop->type === ShopTypeEnum::DROPSHIPPING
            && app()->environment(['local', 'testing']);

        return match (true) {
            $alreadyReported => __('I have added this to :reference, which our engineers are already looking into.', ['reference' => $ticket->reference], $locale),
            $canFollow       => __('I have passed this to our engineers as :reference. You can follow it under Tickets in your account.', ['reference' => $ticket->reference], $locale),
            default          => __('I have passed this to our engineers as :reference.', ['reference' => $ticket->reference], $locale),
        };
    }

    private function addToOpen(ShoppingAssistantConversation $conversation, string $details, string $words, ?WebUser $webUser): ?Ticket
    {
        $open = Ticket::where('type', TicketTypeEnum::MERLIN)
            ->where('customer_id', $conversation->customer_id)
            ->whereNotIn('status', [TicketStatusEnum::RESOLVED, TicketStatusEnum::CANCELLED])
            ->latest('id')
            ->first();

        if ($open && $webUser) {
            StoreTicketComment::make()->handle($open, $webUser, ['body' => trim("Merlin: $details\n\n$words")]);
        }

        return $open;
    }

    /**
     * @param  array<string, mixed>  $triage
     */
    private function store(ShoppingAssistantConversation $conversation, string $summary, string $description, TicketKindEnum $kind, ?WebUser $webUser, array $triage): Ticket
    {
        $customer = $conversation->customer;

        return StoreTicket::make()->action($conversation->group, array_filter([
            'type'            => TicketTypeEnum::MERLIN->value,
            'subject'         => mb_substr($summary, 0, 255),
            'description'     => $description,
            'kind'            => $kind->value,
            'module'          => $customer->shop->type === ShopTypeEnum::DROPSHIPPING ? TicketModuleEnum::DROPSHIPPING->value : null,
            'organisation_id' => $conversation->organisation_id,
            'shop_id'         => $conversation->shop_id,
            'customer_id'     => $customer->id,
            'reporter_type'   => $webUser ? 'WebUser' : null,
            'reporter_id'     => $webUser?->id,
            'source_type'     => 'ShoppingAssistantConversation',
            'source_id'       => $conversation->id,
            'source_channel'  => TicketSourceChannelEnum::WEBSITE->value,
            'tags'            => ['merlin'],
            'data'            => ['merlin_triage' => $triage],
        ], fn ($value) => $value !== null));
    }

    private function description(ShoppingAssistantConversation $conversation, string $details, string $words, ?string $channels, string $whyOnlyUs): string
    {
        $customer = $conversation->customer;
        $parts    = [
            'Raised by Merlin from a conversation with the customer. The customer already tried the fix in our guides.',
            "Why only engineers can fix it:\n$whyOnlyUs",
            "What the customer reports:\n$details",
            "The customer's words:\n> ".str_replace("\n", "\n> ", trim($words)),
        ];

        if ($channels !== null) {
            $parts[] = "Sales channels:\n".($channels ?: 'none');
        }

        $parts[] = 'Conversation: '.route('grp.org.shops.show.ai_salesman.conversations.show', [$customer->organisation->slug, $customer->shop->slug, $conversation->ulid]);

        return implode("\n\n", $parts);
    }

    /**
     * How sure an independent model is that this is a defect only our engineers can fix; null when
     * it cannot be asked, which sends the ticket to customer services.
     */
    private function confidenceOnlyUs(string $summary, string $details, string $words, string $whyOnlyUs, ?string $channels): ?float
    {
        return AskJev::make()->noul(
            array_filter(['summary' => $summary, 'details' => $details, 'customer_words' => $words, 'why_only_engineers' => $whyOnlyUs, 'sales_channels' => $channels]),
            'Engineering triage for the ordering and dropshipping platform of a wholesale supplier. The customer already tried the documented fix. Is this a real defect that only our engineers can fix?',
            'A defect in our own system, integration code or data that the customer and customer services cannot fix',
            'User error, a question, a setting the customer can change, a problem on their own store or marketplace, something customer services can sort out, or not enough evidence'
        );
    }

    /**
     * How sure an independent model is that the customer reports something of ours not working, as
     * opposed to asking a question a person in our team can answer; null when it cannot be asked,
     * which leaves the question with the gurus.
     */
    private function confidenceFault(string $question, string $words, ?string $channels): ?float
    {
        return AskJev::make()->noul(
            array_filter(['question' => $question, 'customer_words' => $words, 'sales_channels' => $channels]),
            'A customer of a wholesale and dropshipping supplier wrote this to the supplier\'s shopping assistant, which could not answer it. Does the customer report that something of the supplier\'s does not work as it should for them (a product upload or sync to their store or marketplace, stock, prices or photos sent to it, orders not arriving, tracking, the connection, an error on our website or in their account), so that engineers must look at it?',
            'They report something of ours not working or showing wrong for them, which engineers must look into.',
            'It is a question a person in the supplier\'s team can answer: about products, prices, policy, samples, delivery, how to do something, or anything that is not a fault on the supplier\'s side.'
        );
    }

    private function channels(ShoppingAssistantConversation $conversation): string
    {
        return DB::table('customer_sales_channels')
            ->join('platforms', 'platforms.id', '=', 'customer_sales_channels.platform_id')
            ->where('customer_sales_channels.customer_id', $conversation->customer_id)
            ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN->value)
            ->orderBy('customer_sales_channels.id')
            ->get(['customer_sales_channels.reference', 'customer_sales_channels.name', 'platforms.name as platform', 'customer_sales_channels.state', 'customer_sales_channels.connection_status', 'customer_sales_channels.connection_failure', 'customer_sales_channels.number_portfolios', 'customer_sales_channels.number_portfolio_broken', 'customer_sales_channels.number_orders', 'customer_sales_channels.last_order_created_at', 'customer_sales_channels.ping_error_count', 'customer_sales_channels.is_down'])
            ->map(fn ($channel) => "- $channel->platform ".($channel->name ?: $channel->reference).': state '.($channel->state ?? '-').', connection '.($channel->connection_status ?? '-').($channel->connection_failure ? " ($channel->connection_failure)" : '').", products $channel->number_portfolios ($channel->number_portfolio_broken broken), orders $channel->number_orders, last order ".($channel->last_order_created_at ?? 'never').", ping errors $channel->ping_error_count".($channel->is_down ? ', down' : ''))
            ->implode("\n");
    }
}
