<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Catalogue\Search\SearchCatalogueByMeaning;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\CRM\RangeAdvisor\GetChristmasBestSellers;
use App\Actions\CRM\RangeAdvisor\GetCustomerRangeGaps;
use App\Actions\CRM\RangeAdvisor\GetSegmentBestSellers;
use App\Actions\CRM\RangeAdvisor\RebuildRangeAdvisorSales;
use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Actions\Iris\Basket\StoreEcomBasketTransactions;
use App\Actions\Tasks\GetAikuAssistant;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Helpers\NaturalLanguage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\CRM\Customer\CustomerShopTypeSourceEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantAnswerKindEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantConversationStatusEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantHandOverReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotRecipient;
use Illuminate\Support\Str;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The tools the shopping assistant model may call, and their executor.
 *
 * What the model sees of a product is only its id, name, family name and id and a reason code (plus
 * the month it was last ordered, for the customer's own forgotten products). family_information
 * adds the family's own website description, so a question about a range is answered from our
 * text, and names that family so the answer can link to its page. Never codes or names of
 * other customers, buyer counts, revenue, segment sizes, prices, discounts or stock figures: the
 * payload is built field by field, never from the model's attributes.
 *
 * Every product a tool returns is live, in stock, priced and visible to the customer. search_products
 * also names, apart and without ids, the products asked for that are out of stock, so the customer
 * hears "out of stock" rather than "not found"; they can never be presented. A code typed for a product
 * we no longer sell names that product apart, without an id, and the meaning search runs on its name
 * instead of the code, so the alternatives are like the old product rather than a guess from the code.
 *
 * The best-seller tools take an optional kind of product ("soap"): only best sellers among the close
 * matches of a meaning search for it are kept, so "your best-selling soaps" is answerable.
 * present_products is never executed: AskShoppingAssistant reads its arguments as the final answer.
 *
 * add_to_basket puts one of each product in the customer's basket, only products already shown in
 * this conversation, or found by a tool in the same turn, and not yet in the basket, through the storefront's own bulk add, so stock,
 * visibility and order limits are checked the same way. clear_conversation hides the conversation
 * from the customer for good; the record stays for staff.
 *
 * better_price_options and big_order_lead serve a customer who wants a better price. The first
 * adds sentences written here: the customer's Gold Reward membership and until when, how to get it,
 * a member-price amnesty running in the shop, and the order size from which our team calls to talk
 * about terms. The second saves a planned order of at least that size as a lead for the
 * organisation's customer services team (StoreShoppingAssistantSalesLead); the model never sees the
 * threshold and never promises a discount.
 *
 * products_on_offer lists the products whose card discount is a sale running now (a category-offers
 * campaign such as Black Friday, never member prices), optionally of one kind; the model never gets a
 * sale name or a percentage.
 *
 * product_documents gives one product's public documents (SDS, IFRA, allergen declaration...) as
 * download links under the reply, and its CPNP number and ingredients as a sentence written here,
 * the same documents and details the product page shows.
 *
 * restock_information says when an out of stock product the customer names is expected back, from
 * what is on order (GetProductRestockEstimate), as a sentence written here with a half-month window,
 * only when the date is reliable. ask_purchasing_team, after the customer said yes, passes a
 * restock question with no reliable answer to the organisation's purchasing people
 * (StoreShoppingAssistantRestockQuestion); their answer reaches the customer by email.
 *
 * shop_information answers a general question about buying from the shop (payment, delivery rules,
 * returns, ordering, the account, terms) from the shop's knowledge base, found by meaning
 * (SearchShopKnowledge): up to four texts, each with its id, and `sources` for the turn, which
 * AskShoppingAssistant checks the answer cites. With no text it tells the model to offer a person,
 * and when the search is not available it returns the tool error, so the model never answers a
 * shop question from its own knowledge.
 *
 * recent_emails_to_customer gives the newsletters and marketing emails this customer was sent in the
 * last RECENT_EMAIL_DAYS days (their mailshot_recipients rows), newest first, as plain text with the
 * date sent, so "the offer in your email" is answered from what they actually received. Each email
 * is a source the answer cites like a shop_information text, so its figures pass the number rule;
 * its id is the mailshot id negated, which never collides with a knowledge entry id.
 *
 * dispatch_times and shipping_cost never give the model a figure: each returns a `fact`, a sentence
 * written here from our data in the shop's language, which AskShoppingAssistant puts under the
 * model's reply. Shipping is read from the customer's own basket, where the system has already
 * worked it out; nothing is estimated.
 */
class GetShoppingAssistantTools
{
    use AsAction;
    use WithRecommendableProducts;

    /**
     * Dropshipping customers have no basket to fill or ship: they add products to the portfolios of
     * their sales channels on the product cards, and each order is one of their own customers'.
     */
    public const array NOT_FOR_DROPSHIPPING = ['add_to_basket', 'shipping_cost', 'big_order_lead'];

    public const array ENGINEER_TICKET_KINDS = ['bug', 'support', 'data_integrity'];

    public const string PRESENT_PRODUCTS = 'present_products';

    public const int MAX_LIMIT = 24;

    public const int DEFAULT_LIMIT = 12;

    public const int USUAL_PER_GROUP = 8;

    public const int MAX_PER_GROUP = 12;

    public const int MAX_FOLLOW_UPS = 3;

    public const int MAX_OUT_OF_STOCK = 3;

    public const array PRICE_SORTS = ['cheapest', 'most_expensive'];

    private const int PRICE_SORT_POOL = 40;

    private const int MAX_DESCRIPTION = 1500;

    private const float PRICE_SORT_MARGIN = 0.12;

    private const int KIND_POOL = 600;

    private const float KIND_MARGIN = 0.06;

    public const int SHOP_PROFILE_MAX_LENGTH = 500;

    public const int RECENT_EMAIL_DAYS = 30;

    private const int RECENT_EMAIL_LIMIT = 5;

    private const int RECENT_EMAIL_CHARACTERS = 6000;

    private const array SEGMENT_REASON_CODES = [
        RebuildRangeAdvisorSales::LEVEL_TYPE_AREA    => 'similar_shops_area',
        RebuildRangeAdvisorSales::LEVEL_TYPE_COUNTRY => 'similar_shops_country',
        RebuildRangeAdvisorSales::LEVEL_TYPE_GROUP   => 'similar_shops_group',
        RebuildRangeAdvisorSales::LEVEL_TYPE         => 'similar_shops',
        RebuildRangeAdvisorSales::LEVEL_AREA         => 'shops_area',
        RebuildRangeAdvisorSales::LEVEL_COUNTRY      => 'shops_country',
        RebuildRangeAdvisorSales::LEVEL_GROUP        => 'shops_group',
        RebuildRangeAdvisorSales::LEVEL_SHOP         => 'best_seller',
    ];

    /**
     * @return array<int, array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function definitions(bool $dropshipping = false): array
    {
        if ($dropshipping) {
            return array_values(array_filter($this->definitions(), fn (array $tool) => !in_array($tool['function']['name'], self::NOT_FOR_DROPSHIPPING, true)));
        }

        $limit = [
            'type'        => 'integer',
            'minimum'     => 1,
            'maximum'     => self::MAX_LIMIT,
            'description' => 'How many products to return, default '.self::DEFAULT_LIMIT.'.',
        ];

        $kind = [
            'type'        => 'string',
            'description' => 'Only when the customer asks about one kind of product or one range ("soap", "tarot cards", "solid soap loaves"): in their words, to get only the best sellers of that kind.',
        ];

        $productIds = fn (string $description) => [
            'type'        => 'array',
            'items'       => ['type' => 'integer'],
            'maxItems'    => self::MAX_PER_GROUP,
            'description' => $description,
        ];

        return [
            $this->tool(
                'search_products',
                'Search the catalogue for products matching what the customer is looking for. Use it whenever the customer names a product, a product code, a theme, an occasion or a kind of item. Returns products with id, name, family and why; exact_code_match marks the product whose code the customer typed and family_code_match the products of the family whose code they typed; matched_families names that family even when none of its products can be shown. It may also return out_of_stock: products we sell that match but cannot be ordered right now. With sort the matches come ordered by price; with sort and an empty query, the whole catalogue.',
                [
                    'query' => ['type' => 'string', 'description' => 'What the customer is looking for, in their words. May be empty only together with sort.'],
                    'sort'  => ['type' => 'string', 'enum' => self::PRICE_SORTS, 'description' => 'Only when the customer asks for the cheapest or the most expensive: order the matches by price instead of by relevance.'],
                    'limit' => $limit,
                ],
                ['query']
            ),
            $this->tool(
                'best_sellers_for_similar_shops',
                'Best sellers among the shops closest to this customer: the same kind of shop when we know theirs, otherwise every shop, nearby first. Each product\'s why says exactly which shops it is about. Use it when the customer asks what sells well, wants ideas, or is starting or refreshing their range; with kind when they ask for the best sellers of one kind of product. Returns the top of the ranking, best first, not a full list. Returns products with id, name, family and why.',
                ['kind' => $kind, 'limit' => $limit]
            ),
            $this->tool(
                'best_sellers_last_christmas',
                'Products that sold mostly in the run-up to last Christmas (September to December) in this shop, whether or not their name says Christmas. These are our sales to retailers, not this customer\'s own sales: say they sold well last Christmas, never that they sold in the customer\'s shop. Use it whenever the customer asks about Christmas, festive or seasonal stock, Christmas gifts, a Christmas stall or fair, or what sells at Christmas; with kind when they ask about one kind of product. Returns the top of the ranking, best first, not a full list. Returns products with id, name, family and why.',
                ['kind' => $kind, 'limit' => $limit]
            ),
            $this->tool(
                'missing_from_range',
                'Products that sell well in the shops closest to this customer (the same kind of shop when we know theirs, otherwise every shop) but that this customer has never ordered and, on a dropshipping shop, does not list in their stores. Each product\'s why says exactly which shops it is about. Use it when the customer asks what they are missing or how to widen their range. Returns products with id, name, family and why.',
                ['limit' => $limit]
            ),
            $this->tool(
                'products_they_stopped_ordering',
                'Products this customer used to order but has not ordered in the last 12 months. Use it when the customer asks what to reorder or mentions products they had before. Returns products with id, name, family, why and last_ordered (year and month).',
                ['limit' => $limit]
            ),
            $this->tool(
                'save_shop_type',
                'Save the kind of shop the customer runs, when they tell you and it is not known yet. Call it at most once. Returns whether it was saved or was already known. Returns no products.',
                [
                    'shop_type' => ['type' => 'string', 'enum' => CustomerShopTypeEnum::values(), 'description' => 'The kind of shop the customer said they run.'],
                ],
                ['shop_type']
            ),
            $this->tool(
                'remember_about_shop',
                'Keep lasting facts the customer told you about their shop for later conversations: what they sell, where they sell (a shop, online, markets, events), who their customers are, their style or price level, ranges they want to try or never want. Pass the whole updated list, replacing what is remembered. Returns no products.',
                [
                    'facts' => ['type' => 'string', 'maxLength' => self::SHOP_PROFILE_MAX_LENGTH, 'description' => 'Short facts separated by "; ", in the language of the conversation, written as facts about the shop (e.g. "florist; also sells online; tourist customers; no incense"). No numbers, no names of people, nothing about payments or their account.'],
                ],
                ['facts']
            ),
            $this->tool(
                'add_to_basket',
                'Put products in the customer\'s basket, one of each, only when the customer asks you to. Without product_ids it adds every product of your previous answer. Only products shown in this conversation, or returned by a tool in this same turn, can be added: when the customer names a product to add, search for it first, then add it by its id. Returns the names added, already in the basket and those that could not be added. Returns no products.',
                [
                    'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => self::MAX_PER_GROUP * 2, 'description' => 'Ids of shown products to add, when the customer asked for only some of them.'],
                ]
            ),
            $this->tool(
                'family_information',
                'What we say about a product family (range) on the website: its description. Use it when the customer asks about a range or a product rather than asking for products: what it is, why it is special, what it is made of, how it is used. Take family_id from a product returned by another tool. The page adds a link to the family page under your reply. Returns no products.',
                [
                    'family_id' => ['type' => 'integer', 'description' => 'family_id of a product returned by another tool.'],
                ],
                ['family_id']
            ),
            $this->tool(
                'ask_our_team',
                'Ask a person in our team, whenever you are in doubt, a question our texts, family descriptions and product documents do not clearly answer: about buying from us (samples, a discount code, a document that is not online, a special request) or about our products (how to use one, whether it suits a purpose, safety, other sizes or colours). Better to ask than to guess: their answer is kept so you can answer it yourself next time. Not for the customer\'s own orders, invoices or deliveries, and not when they ask for a person. Returns whether it was passed on and whether the answer can still appear in this conversation within minutes. Returns no products.',
                [
                    'question' => ['type' => 'string', 'description' => 'The customer\'s question in one sentence, in English.'],
                ],
                ['question']
            ),
            $this->tool(
                'product_documents',
                'The public documents of one product and its compliance details: SDS (safety data sheet), IFRA certificate, allergen declaration, declaration of conformity, CPSR, test reports and how-to-use guides, plus its CPNP number, ingredients and countries of origin. Use it whenever the customer asks for any of these, for a product or a kind of product. The download links, the CPNP number and the ingredients are added under your reply by the system; you get only which documents exist. Returns no products.',
                [
                    'query' => ['type' => 'string', 'description' => 'The product the customer asked about, by name or code, in their words.'],
                ],
                ['query']
            ),
            $this->tool(
                'dispatch_times',
                'How quickly this shop dispatches orders, from the last 30 days. Use it when the customer asks how fast orders are sent, dispatched or shipped out. The sentence with the figure is added under your reply by the system; you get no figure. Returns no products.',
                []
            ),
            $this->tool(
                'shipping_cost',
                'What shipping costs for the customer\'s current basket. Use it when the customer asks about shipping, postage, carriage or delivery cost. The sentence with the amount is added under your reply by the system; you get no figure. Returns no products.',
                []
            ),
            $this->tool(
                'products_on_offer',
                'Products on a sale or promotion running now (Black Friday, a monthly offer...), in stock; the discount shows on each product card. Use it whenever the customer asks what is on sale, on offer or in a promotion, or for the products of a sale they name, which are all of them; with kind when they want one kind of product on sale. Returns products with id, name, family and why.',
                ['kind' => ['type' => 'string', 'description' => 'Only when the customer wants one kind of product on sale ("candles"), in their words; never the name of the sale.'], 'limit' => $limit]
            ),
            $this->tool(
                'better_price_options',
                'What can lower the price for this customer. Use it whenever the customer asks for a better price, a discount code, a deal or a special price, or says something is too expensive; not when they only want to see what is on sale, which is products_on_offer. Sentences about their Gold Reward membership, any member-price offer running now, and the order size from which our team calls to discuss better terms are added under your reply by the system; you get no figures or dates. Returns no products.',
                []
            ),
            $this->tool(
                'big_order_lead',
                'Pass on to our team a big order the customer plans. Use it whenever the customer says how much they plan to order (or would order for a better price), and again when they answer whether they want a call, with their phone or a good time. The system decides whether the amount is big enough for a call. Returns no products.',
                [
                    'planned_amount'          => ['type' => 'number', 'description' => 'The order value the customer said they plan, as a plain number ("about 3k" is 3000).'],
                    'currency'                => ['type' => 'string', 'description' => 'ISO code of the currency the customer meant (EUR, GBP, PLN...), when they said one; otherwise leave it out.'],
                    'customer_agreed_to_call' => ['type' => 'boolean', 'description' => 'True only when the customer has said yes to a call from our team in this conversation.'],
                    'phone'                   => ['type' => 'string', 'description' => 'A phone number the customer gave in this conversation, exactly as written; leave it out to use the one on their account.'],
                    'best_time'               => ['type' => 'string', 'description' => 'When the customer said suits them for a call, in their words.'],
                    'details'                 => ['type' => 'string', 'description' => 'In one sentence, what they plan to order and what they asked for.'],
                ],
                ['planned_amount', 'customer_agreed_to_call']
            ),
            $this->tool(
                'restock_information',
                'When an out of stock product is expected back. Use it whenever the customer asks when a product will be back in stock, restocked or delivered again, or when new stock or new designs of a product arrive. A sentence with the expected period, from our orders, is added under your reply by the system when we have a reliable date; you get no dates. Returns no products.',
                [
                    'query' => ['type' => 'string', 'description' => 'The product the customer asked about, by name or code, in their words.'],
                ],
                ['query']
            ),
            $this->tool(
                'ask_purchasing_team',
                'Pass a stock question we cannot answer to our purchasing team, who reply to the customer by email. Use it only after restock_information gave no reliable date and the customer said yes to an answer from the purchasing team. Returns no products.',
                [
                    'query'                    => ['type' => 'string', 'description' => 'The product the question is about, by name or code; leave it empty when the question is not about one product.'],
                    'question'                 => ['type' => 'string', 'description' => 'The customer\'s question in one sentence, in English.'],
                    'customer_agreed'          => ['type' => 'boolean', 'description' => 'True only when the customer said yes to an answer from the purchasing team.'],
                ],
                ['question', 'customer_agreed']
            ),
            $this->tool(
                'report_to_engineers',
                'Send a real problem straight to our engineers as a ticket, skipping customer services: something broken on our side that only our engineers can fix (a bug on our website or in their account, an integration with their store or marketplace that misbehaves on our side, products or stock not syncing, orders not arriving, records of ours that look wrong), after the customer tried the fix shop_information gives. The system checks it independently and only raises the ticket when it is sure; otherwise it tells you to offer a person. Not for how-to questions, product questions, user mistakes, settings the customer can change, problems on their own store or platform, the customer\'s own order status or delivery, or complaints about a product. Call it once per problem. The system writes the ticket reference under your reply. Returns no products.',
                [
                    'tried_fix'   => ['type' => 'boolean', 'description' => 'True only when the customer said they already followed the fix our texts give for this problem, or our texts have none and they followed your steps.'],
                    'why_only_us' => ['type' => 'string', 'maxLength' => 500, 'description' => 'In English, the evidence that only our engineers can fix it (e.g. "orders stopped for a store that shows connected and was reconnected"). Empty when you are not sure; never guess.'],
                    'summary' => ['type' => 'string', 'maxLength' => 200, 'description' => 'One English sentence naming the problem for an engineer, e.g. "Shopify orders stopped arriving since Monday".'],
                    'details' => ['type' => 'string', 'maxLength' => 2000, 'description' => 'In English: what the customer reported, which platform, store and products, since when, what they already tried and what you checked.'],
                    'kind'    => ['type' => 'string', 'enum' => self::ENGINEER_TICKET_KINDS, 'description' => 'bug: something on our side does not work; support: their integration or account needs an engineer to look at it; data_integrity: our records look wrong and need checking.'],
                ],
                ['tried_fix', 'why_only_us', 'summary', 'details', 'kind']
            ),
            $this->tool(
                'shop_information',
                'What our own pages, shop settings and customer service notes say about buying from this shop: payment, delivery and its cost rules, returns, ordering, minimum order, the customer\'s account, our terms. Use it for every general question about the shop that is not a request for products. Returns up to 4 texts, each with an id. Answer only from these texts and list the ids you used in sources. Returns no products.',
                [
                    'question' => ['type' => 'string', 'description' => 'The customer\'s question, in their words.'],
                ],
                ['question']
            ),
            $this->tool(
                'recent_emails_to_customer',
                'The newsletters and marketing emails we sent this customer in the last month, newest first, each with an id, the date sent and its text. Use it when the customer mentions an email, newsletter, offer, code or news we sent them. Answer only from these emails and list the ids you used in sources. Returns no products.',
                []
            ),
            $this->tool(
                'clear_conversation',
                'Clear this conversation when the customer asks to delete, clear or forget it. The page then tells them it was cleared. Returns no products.',
                []
            ),
            $this->tool(
                self::PRESENT_PRODUCTS,
                'Give your final answer. Call it once, as your last step, every time: also when you show no products, ask a question or decline (leave must_haves and add_ons empty), so the customer always gets follow_ups to tap. Use only ids returned by the other tools.',
                [
                    'reply'        => ['type' => 'string', 'description' => 'Two or three short sentences for the customer, with no ids, prices, discounts or stock, and no numbers other than figures stated in the texts you cite in sources.'],
                    'answer_kind'  => ['type' => 'string', 'enum' => ShoppingAssistantAnswerKindEnum::declarable(), 'description' => 'products when you show or talk about products or ranges; shop_answer when you answer a general question from texts shop_information returned or a question about an email from recent_emails_to_customer (sources is then required); question when you only ask the customer something; offer_person when you cannot answer and a person should (offer_reason is then required); off_topic when you decline something that has nothing to do with this shop; coaching when, on a dropshipping shop, you explain a general dropshipping or online selling idea from your own knowledge.'],
                    'sources'      => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ids, exactly as given, of the shop_information texts or recent_emails_to_customer emails your answer rests on. Required with answer_kind shop_answer.'],
                    'offer_reason' => ['type' => 'string', 'enum' => array_column(ShoppingAssistantHandOverReasonEnum::cases(), 'value'), 'description' => 'not_sure: you could not answer from the texts or have none; order_question: the customer asks about their own order, invoice, payment, refund or delivery; asked_for_person: the customer asks for a person.'],
                    'must_haves' => $productIds('Ids of products that fit the request or sell well for similar shops, usually up to '.self::USUAL_PER_GROUP.', at most '.self::MAX_PER_GROUP.' when the customer asks for more.'),
                    'add_ons'    => $productIds('Ids of complementary or previously ordered products, usually up to '.self::USUAL_PER_GROUP.', at most '.self::MAX_PER_GROUP.' when the customer asks for more.'),
                    'follow_ups' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'maxItems'    => self::MAX_FOLLOW_UPS,
                        'description' => 'Two or three short questions the customer could ask next, about products you can find for them. Always give them, also when you decline or show nothing.',
                    ],
                ],
                ['reply', 'answer_kind']
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, int>  $foundProductIds  ids the tools returned earlier in this turn
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    public function execute(ShoppingAssistantConversation $conversation, string $name, array $arguments, array $foundProductIds = []): array
    {
        $customer = $conversation->customer;

        if (!$customer) {
            return $this->error('This conversation has no customer.');
        }

        if (in_array($name, self::NOT_FOR_DROPSHIPPING, true) && self::isDropshipping($customer)) {
            return $this->error("$name is not available on a dropshipping shop.");
        }

        try {
            return match ($name) {
                'search_products'                => $this->searchProducts($customer, $arguments, $conversation->language_code),
                'best_sellers_for_similar_shops' => $this->bestSellers($customer, $arguments, $conversation->language_code),
                'best_sellers_last_christmas'    => $this->christmasBestSellers($customer, $arguments, $conversation->language_code),
                'missing_from_range'             => $this->missingFromRange($customer, $arguments, $conversation->language_code),
                'products_they_stopped_ordering' => $this->stoppedOrdering($customer, $arguments, $conversation->language_code),
                'save_shop_type'                 => $this->saveShopType($conversation, $customer, $arguments),
                'remember_about_shop'            => $this->rememberAboutShop($conversation, $customer, $arguments),
                'add_to_basket'                  => $this->addToBasket($conversation, $customer, $arguments, $foundProductIds),
                'clear_conversation'             => $this->clearConversation($conversation),
                'better_price_options'           => $this->betterPriceOptions($customer, $conversation->language_code),
                'products_on_offer'              => $this->productsOnOffer($customer, $arguments, $conversation->language_code),
                'big_order_lead'                 => $this->bigOrderLead($conversation, $arguments),
                'restock_information'            => $this->restockInformation($customer, $arguments, $conversation->language_code),
                'ask_purchasing_team'            => $this->askPurchasingTeam($conversation, $customer, $arguments),
                'dispatch_times'                 => $this->dispatchTimes($customer, $conversation->language_code),
                'family_information'             => $this->familyInformation($customer, $arguments),
                'product_documents'              => $this->productDocuments($customer, $arguments, $conversation->language_code),
                'ask_our_team'                   => $this->askOurTeam($conversation, $arguments),
                'report_to_engineers'            => $this->reportToEngineers($conversation, $arguments),
                'shipping_cost'                  => $this->shippingCost($customer, $conversation->language_code),
                'shop_information'               => $this->shopInformation($customer, $arguments),
                'recent_emails_to_customer'      => $this->recentEmailsToCustomer($customer),
                default                          => $this->error("Unknown tool $name."),
            };
        } catch (Throwable $exception) {
            Log::warning('Shopping assistant tool '.$name.' failed: '.$exception->getMessage());

            return $this->error('The tool failed, try another one.');
        }
    }

    public function reasonText(string $reasonCode, Customer $customer, ?string $locale = null): ?string
    {
        $country = fn (): ?string => DB::table('countries')->where('code', data_get($customer->location, 0))->value('name');

        return match ($reasonCode) {
            'similar_shops_area'    => __('Popular with shops like yours in your area', [], $locale),
            'similar_shops_country' => ($name = $country()) ? __('Popular with shops like yours in :country', ['country' => $name], $locale) : __('Popular with shops like yours', [], $locale),
            'similar_shops_group'   => __('Popular with shops like yours in your region', [], $locale),
            'similar_shops'         => __('Popular with shops like yours', [], $locale),
            'shops_area'            => __('Popular with shops in your area', [], $locale),
            'shops_country'         => ($name = $country()) ? __('Popular with shops in :country', ['country' => $name], $locale) : __('A best seller', [], $locale),
            'shops_group'           => __('Popular with shops in your region', [], $locale),
            'best_seller'           => __('A best seller', [], $locale),
            'used_to_order'         => __('You used to order this', [], $locale),
            'sold_last_christmas'   => __('Sold well last Christmas', [], $locale),
            'matches_request'       => __('Matches what you asked for', [], $locale),
            'new_arrival'           => __('New in our range', [], $locale),
            default                 => null,
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function searchProducts(Customer $customer, array $arguments, string $locale): array
    {
        $query = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $sort  = in_array($arguments['sort'] ?? null, self::PRICE_SORTS, true) ? $arguments['sort'] : null;

        if ($query === '' && !$sort) {
            return $this->error('query must be a non-empty string.');
        }

        $limit        = $this->limit($arguments);
        $inStock      = fn (Product $product) => $product->available_quantity > 0;
        $byCode       = $query === '' ? collect() : $this->productsByCode($customer, $query);
        $discontinued = $byCode->isEmpty() && $query !== '' ? $this->discontinuedByCode($customer, $query) : collect();
        $byFamily  = $query === '' ? ['products' => collect(), 'exact' => false, 'families' => []] : $this->productsByFamilyCode($customer, $query, $limit);
        $codeIds   = $byCode->pluck('id')->flip();
        $familyIds = $byFamily['exact'] ? $byFamily['products']->pluck('id')->flip() : collect();

        if ($sort) {
            $matches = $query === ''
                ? $this->recommendableProducts($customer->shop, $customer)->select('products.*')->orderBy('products.price', $sort === 'cheapest' ? 'asc' : 'desc')->orderBy('products.id')->limit($limit)->get()->toBase()
                : $this->closeMatches(SearchCatalogueByMeaning::run($customer->shop, $query, $customer, self::PRICE_SORT_POOL))
                    ->concat($byFamily['products'])->unique('id')
                    ->sortBy(fn (Product $product) => (float) $product->price, SORT_REGULAR, $sort !== 'cheapest')->values()->take($limit);

            $result = $this->productsResult($customer, $matches, fn () => 'matches_request', $locale);

            $result['content']['note'] = 'Ordered by price, '.($sort === 'cheapest' ? 'lowest' : 'highest').' first. Say so, and never state a price: the cards show them.';

            return $result;
        }

        $nearest = SearchCatalogueByMeaning::run($customer->shop, $discontinued->isNotEmpty() ? $discontinued->first()->name : $query, $customer, $limit + self::MAX_OUT_OF_STOCK, false);

        $outOfStock = (new EloquentCollection($byCode->concat($nearest->take(self::MAX_OUT_OF_STOCK))->reject($inStock)->unique('id')->take(self::MAX_OUT_OF_STOCK)->all()))->loadMissing('family');

        $result = $this->productsResult($customer, $byCode->concat($byFamily['products'])->concat($nearest)->filter($inStock)->unique('id')->take($limit), fn () => 'matches_request', $locale);

        $result['content']['products'] = array_map(fn (array $item) => match (true) {
            isset($codeIds[$item['id']])   => $item + ['exact_code_match' => true],
            isset($familyIds[$item['id']]) => $item + ['family_code_match' => true],
            default                        => $item,
        }, $result['content']['products']);
        if ($byFamily['families']) {
            $result['content']['matched_families'] = $byFamily['families'];
        }
        if ($outOfStock->isNotEmpty()) {
            $result['content']['out_of_stock'] = $outOfStock->map(fn (Product $product) => array_filter(['name' => $product->name, 'family' => $product->family?->name, 'exact_code_match' => isset($codeIds[$product->id])]))->values()->all();
        }
        if ($discontinued->isNotEmpty()) {
            $result['content']['discontinued'] = $discontinued->map(fn (Product $product) => array_filter(['name' => $product->name, 'family' => $product->family?->name]))->values()->all();
            $result['content']['note']         = 'The code the customer typed is a product we no longer sell, listed under discontinued. Say by its name that it has been discontinued, never guess what else the code could be, and present the products returned, which are the closest to it.';
        }
        $named = $outOfStock->concat($discontinued);
        if ($named->isNotEmpty()) {
            $result['names'] = $named->flatMap(fn (Product $product) => [$product->name, $product->family?->name])->filter()->values()->all();
        }

        return $result;
    }

    /**
     * A product of this shop whose code the customer typed and that has been discontinued, when no
     * other product of the shop still uses that code. One made for another customer is never named.
     *
     * @return EloquentCollection<int, Product>
     */
    private function discontinuedByCode(Customer $customer, string $query): EloquentCollection
    {
        $candidates = $this->codeCandidates($query)->filter(fn (string $candidate) => preg_match('/\d/', $candidate) && preg_match('/[a-z]/', $candidate))->values();

        if ($candidates->isEmpty()) {
            return new EloquentCollection();
        }

        return Product::query()
            ->where('products.shop_id', $customer->shop_id)
            ->where('products.state', ProductStateEnum::DISCONTINUED->value)
            ->where('products.is_minion_variant', false)
            ->whereIn(DB::raw("lower(regexp_replace(products.code, '[^A-Za-z0-9]', '', 'g'))"), $candidates->all())
            ->whereNotExists(fn ($query) => $query->from('products as live')
                ->where('live.shop_id', $customer->shop_id)
                ->where('live.state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->whereNull('live.deleted_at')
                ->whereRaw("lower(regexp_replace(live.code, '[^A-Za-z0-9]', '', 'g')) = lower(regexp_replace(products.code, '[^A-Za-z0-9]', '', 'g'))"))
            ->visibleToCustomer($customer->id)
            ->with('family')
            ->limit(self::MAX_OUT_OF_STOCK)
            ->get();
    }

    /**
     * Ids of the products of a kind ("soap"), for the best-seller tools: those within KIND_MARGIN of the
     * closest match. On the UK catalogue (12 Nov 2026) that kept 181 for soap, 130 for candles, 8 for
     * tarot cards, none reaching the pool; the wider price-sort margin let in room sprays for soap.
     * Null when the search gave nothing at all (the embedding call failed), so the tool reports an
     * error instead of telling the customer that none of that kind sells.
     *
     * @return ?array<int, int>
     */
    private function productIdsOfKind(Customer $customer, string $kind): ?array
    {
        $nearest = SearchCatalogueByMeaning::run($customer->shop, $kind, $customer, self::KIND_POOL);
        if ($nearest->isEmpty()) {
            return null;
        }
        $best = (float) $nearest->min('distance');

        return $nearest->filter(fn (Product $product) => (float) $product->distance <= $best + self::KIND_MARGIN)->pluck('id')->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function kindOf(array $arguments): string
    {
        return is_string($arguments['kind'] ?? null) ? trim($arguments['kind']) : '';
    }

    /**
     * @param  array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}  $result
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function withKindNote(array $result, string $kind): array
    {
        if ($kind === '') {
            return $result;
        }

        $result['content']['note'] = $result['products']
            ? 'Only the best sellers matching "'.$kind.'", best first. This is the top of the ranking, not every product of that kind: never say you checked a full list.'
            : 'None of the products matching "'.$kind.'" is among the best sellers. Say so plainly, and offer to show the matching products, which you find with search_products.';

        return $result;
    }

    /**
     * For a price ordering only the matches close to the best one are kept, so a narrow request
     * ("cheapest salt lamps") is not topped up with unrelated cheap products.
     *
     * ponytail: a fixed cosine margin over the best match; tune it, or rank by a relevance score,
     * when the price-ordered answers show unrelated products.
     *
     * @param  Collection<int, Product>  $nearest
     *
     * @return Collection<int, Product>
     */
    private function closeMatches(Collection $nearest): Collection
    {
        $best = (float) $nearest->min('distance');

        return $nearest->filter(fn (Product $product) => (float) $product->distance <= $best + self::PRICE_SORT_MARGIN)->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function codeCandidates(string $query): Collection
    {
        $words      = preg_split('/\s+/', mb_strtolower(trim($query)));
        $candidates = [implode('', $words)];
        foreach ($words as $index => $word) {
            $candidates[] = $word;
            $candidates[] = $word.($words[$index + 1] ?? '');
        }

        return collect($candidates)
            ->map(fn (string $candidate) => preg_replace('/[^a-z0-9]/', '', $candidate))
            ->filter(fn (string $candidate) => strlen($candidate) >= 3)
            ->unique()
            ->values();
    }

    /**
     * Customers type product codes loosely ("tiba 04" for TibA-04, "iconic -29" for Iconic-29), and
     * the meaning search knows nothing of codes: codes are compared with everything but letters and
     * digits removed, against the whole query, each word and each pair of neighbouring words.
     *
     * ponytail: no index on the normalised code, one scan of the shop's products per search; add an
     * expression index if searches slow down.
     *
     * @return Collection<int, Product>
     */
    private function productsByCode(Customer $customer, string $query): Collection
    {
        $candidates = $this->codeCandidates($query)->filter(fn (string $candidate) => preg_match('/\d/', $candidate) && preg_match('/[a-z]/', $candidate))->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        return $this->recommendableProducts($customer->shop, $customer, false)
            ->select('products.*')
            ->whereIn(DB::raw("lower(regexp_replace(products.code, '[^A-Za-z0-9]', '', 'g'))"), $candidates->all())
            ->limit(self::MAX_OUT_OF_STOCK)
            ->get()
            ->toBase();
    }

    /**
     * A family code typed as the request ("bbskt" for the Bbskt family) gives that family's products
     * in stock. When nothing matches exactly and the request is one word of four letters or more, a
     * family whose code starts with it counts too, but only if it is the only one ("tcan" for TCand);
     * those products lead the results without being marked as an exact match.
     *
     * @return array{products: Collection<int, Product>, exact: bool, families: array<int, array{family_id: int, name: string}>}
     */
    private function productsByFamilyCode(Customer $customer, string $query, int $limit): array
    {
        $candidates = $this->codeCandidates($query);
        $normalised = "lower(regexp_replace(product_categories.code, '[^A-Za-z0-9]', '', 'g'))";
        $families   = fn () => ProductCategory::where('product_categories.shop_id', $customer->shop_id)
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.is_in_website', true)
            ->whereNotIn('product_categories.state', [ProductCategoryStateEnum::DISCONTINUED, ProductCategoryStateEnum::INACTIVE]);

        if ($candidates->isEmpty()) {
            return ['products' => collect(), 'exact' => false, 'families' => []];
        }

        $familyIds = $families()->whereIn(DB::raw($normalised), $candidates->all())->limit(3)->pluck('id');
        $exact     = $familyIds->isNotEmpty();

        if (!$exact && $candidates->count() === 1 && strlen($candidates->first()) >= 4) {
            $starting  = $families()->whereRaw("$normalised like ?", [$candidates->first().'%'])->limit(2)->pluck('id');
            $familyIds = $starting->count() === 1 ? $starting : collect();
        }

        if ($familyIds->isEmpty()) {
            return ['products' => collect(), 'exact' => false, 'families' => []];
        }

        return [
            'products' => $this->recommendableProducts($customer->shop, $customer)->select('products.*')->whereIn('products.family_id', $familyIds)->orderBy('products.code')->limit($limit)->get()->toBase(),
            'exact'    => $exact,
            'families' => ProductCategory::whereIn('id', $familyIds)->get(['id', 'name'])->map(fn (ProductCategory $family) => ['family_id' => $family->id, 'name' => $family->name])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function bestSellers(Customer $customer, array $arguments, string $locale): array
    {
        $kind     = $this->kindOf($arguments);
        $options  = [
            'customer' => $customer,
            'area'     => RebuildRangeAdvisorSales::areaOf($customer),
            'limit'    => $this->limit($arguments),
        ];
        if ($kind !== '') {
            $options['product_ids'] = $this->productIdsOfKind($customer, $kind);
            if ($options['product_ids'] === null) {
                return $this->error('The search for "'.$kind.'" failed. Use search_products instead.');
            }
        }

        $products = $kind !== '' && !$options['product_ids']
            ? collect()
            : GetSegmentBestSellers::run($customer->shop, $customer->shop_type?->value, data_get($customer->location, 0), $options)['items'];

        return $this->withKindNote($this->productsResult($customer, $products, $this->segmentReasonCode(...), $locale), $kind);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function christmasBestSellers(Customer $customer, array $arguments, string $locale): array
    {
        $kind = $this->kindOf($arguments);
        $ids  = $kind !== '' ? $this->productIdsOfKind($customer, $kind) : null;
        if ($kind !== '' && $ids === null) {
            return $this->error('The search for "'.$kind.'" failed. Use search_products instead.');
        }
        $products = GetChristmasBestSellers::run($customer->shop, $customer, $this->limit($arguments), $ids);

        return $this->withKindNote($this->productsResult($customer, $products, fn () => 'sold_last_christmas', $locale), $kind);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function missingFromRange(Customer $customer, array $arguments, string $locale): array
    {
        $products = GetCustomerRangeGaps::run($customer, $this->limit($arguments))['missing'];

        return $this->productsResult($customer, $products, $this->segmentReasonCode(...), $locale);
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function stoppedOrdering(Customer $customer, array $arguments, string $locale): array
    {
        $products = GetCustomerRangeGaps::run($customer, $this->limit($arguments))['forgotten'];

        return $this->productsResult($customer, $products, fn () => 'used_to_order', $locale, true);
    }

    /**
     * Only a shop type the customer has not given, or one we only inferred, is saved; one the
     * customer or staff set is never overwritten.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function saveShopType(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments): array
    {
        $shopType = is_string($arguments['shop_type'] ?? null) ? CustomerShopTypeEnum::tryFrom($arguments['shop_type']) : null;

        if (!$shopType) {
            return $this->error('shop_type must be one of: '.implode(', ', CustomerShopTypeEnum::values()).'.');
        }

        $customer = $customer->fresh();

        if ($customer->shop_type !== null && $customer->shop_type_source !== CustomerShopTypeSourceEnum::INFERRED) {
            return ['content' => ['saved' => false, 'shop_type' => $customer->shop_type->value, 'note' => 'Already known, nothing changed.'], 'products' => [], 'error' => null];
        }

        $customer = UpdateCustomer::make()->action($customer, [
            'shop_type'        => $shopType->value,
            'shop_type_source' => CustomerShopTypeSourceEnum::CUSTOMER->value,
        ]);
        $conversation->setRelation('customer', $customer);

        return ['content' => ['saved' => true, 'shop_type' => $shopType->value], 'products' => [], 'error' => null];
    }

    /**
     * Facts with a figure are refused, as the reply guard only lets the AI repeat numbers the customer
     * typed in the same conversation, and a remembered figure would come back in a later one.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string}
     */
    private function rememberAboutShop(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments): array
    {
        $facts = trim(preg_replace('/\s+/u', ' ', (string) ($arguments['facts'] ?? '')));

        if ($facts === '') {
            return $this->error('facts is empty.');
        }
        if (preg_match('/\d/u', $facts)) {
            return $this->error('Leave out numbers: keep only facts without figures.');
        }

        $facts = mb_substr($facts, 0, self::SHOP_PROFILE_MAX_LENGTH);
        DB::table('customers')->where('id', $customer->id)->update(['shop_profile' => $facts]);
        $conversation->setRelation('customer', $customer->setAttribute('shop_profile', $facts));

        return ['content' => ['saved' => true], 'products' => [], 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, basket?: array<int, int>}
     */
    private function addToBasket(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments, array $foundProductIds = []): array
    {
        $shown = $conversation->events()->where('type', ShoppingAssistantEventTypeEnum::SHOWN)->orderBy('id')->get(['product_id', 'shopping_assistant_message_id']);
        $ids   = collect(is_array($arguments['product_ids'] ?? null) ? $arguments['product_ids'] : [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            $ids = $shown->where('shopping_assistant_message_id', $shown->max('shopping_assistant_message_id'))->pluck('product_id');
        }

        $ids = $ids->intersect($shown->pluck('product_id')->merge($foundProductIds))->unique()->take(self::MAX_PER_GROUP * 2)->values();

        if ($ids->isEmpty()) {
            return $this->error('No product shown in this conversation matches. Show products first, then add them.');
        }

        $orderId  = Order::where('customer_id', $customer->id)->where('state', OrderStateEnum::CREATING)->value('id');
        $inBasket = $orderId
            ? Transaction::where('order_id', $orderId)->where('model_type', 'Product')->whereIn('model_id', $ids)->where('is_gift', false)->pluck('model_id')
            : collect();
        $toAdd    = $ids->diff($inBasket)->values();

        $lines = $toAdd->isEmpty() ? collect() : collect(StoreEcomBasketTransactions::make()->handle($customer, $toAdd->map(fn (int $id) => ['product_id' => $id, 'quantity' => 1])->all())['lines']);
        $added = $lines->where('status', 'added');

        if ($added->isNotEmpty()) {
            RecordShoppingAssistantBasketAdd::make()->record($customer, $added->map(fn (array $line) => [
                'product_id'     => $line['product_id'],
                'transaction_id' => $line['transaction_id'],
                'quantity'       => $line['quantity_ordered'],
            ])->values()->all(), ShoppingAssistantEventTypeEnum::ADDED);
        }

        $names   = Product::whereIn('id', $ids)->pluck('name', 'id');
        $namesOf = fn (Collection $productIds) => $productIds->map(fn ($id) => $names[$id] ?? null)->filter()->values()->all();

        return [
            'content' => [
                'added'             => $namesOf($added->pluck('product_id')),
                'already_in_basket' => $namesOf($inBasket),
                'could_not_add'     => $namesOf($lines->where('status', 'failed')->pluck('product_id')),
                'note'              => 'One of each was added. Tell the customer what happened in one or two sentences, without counts; they set quantities on the product cards or in the basket.',
            ],
            'products' => [],
            'error'    => null,
            'basket'   => $added->pluck('product_id')->values()->all(),
        ];
    }

    /**
     * Only a family of this shop that is on the website and not discontinued: a family still marked
     * in process can be live and selling (PrEOUL, 11 Nov 2026). The description is plain text, capped.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, families?: array<int, int>, names?: array<int, string>}
     */
    private function familyInformation(Customer $customer, array $arguments): array
    {
        $family = is_numeric($arguments['family_id'] ?? null)
            ? ProductCategory::where('shop_id', $customer->shop_id)
                ->where('type', ProductCategoryTypeEnum::FAMILY)
                ->whereNotIn('state', [ProductCategoryStateEnum::DISCONTINUED, ProductCategoryStateEnum::INACTIVE])
                ->where('is_in_website', true)
                ->find((int) $arguments['family_id'])
            : null;

        if (!$family) {
            return $this->error('family_id must be the family_id of a product returned by another tool.');
        }

        $description = mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $family->description.' '.$family->description_extra)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, self::MAX_DESCRIPTION);

        return [
            'content'  => [
                'family'      => $family->name,
                'description' => $description !== '' ? $description : null,
                'note'        => $description !== ''
                    ? 'Answer from this description in two or three sentences, in your own words. A link to the family page is added under your reply. Do not explain or justify prices: they are on the product cards.'
                    : 'We have no description for this family. Say what its name tells and that the family page, linked under your reply, shows the products.',
            ],
            'products' => [],
            'error'    => null,
            'families' => [$family->id],
            'names'    => array_values(array_filter([$family->name, $description])),
        ];
    }

    /**
     * The shop's knowledge entries nearest to the question. Staff notes and verified facts are marked
     * checked_by_staff: they were written for customer service, some of them because a page of ours is
     * out of date, so the model is told to follow them over a page and to leave out what is only for
     * staff (internal codes, ids, system names, what staff can do, remarks about our own pages).
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, sources?: array<int, array{id: int, kind: string, source_type: string, source_id: ?string, title: string, url: ?string, body: string}>}
     */
    private function shopInformation(Customer $customer, array $arguments): array
    {
        $question = is_string($arguments['question'] ?? null) ? $arguments['question'] : '';
        $entries  = SearchShopKnowledge::run($customer->shop, $question);

        if ($entries === null) {
            return $this->error('The shop information is not available right now. Do not answer from your own knowledge: answer with answer_kind offer_person and offer_reason not_sure.');
        }

        if ($entries->isEmpty()) {
            return [
                'content'  => ['texts' => [], 'note' => 'We have nothing written about this. Do not answer from your own knowledge: answer with answer_kind offer_person and offer_reason not_sure.'],
                'products' => [],
                'error'    => null,
                'sources'  => [],
            ];
        }

        return [
            'content'  => [
                'texts' => $entries->map(fn (ChatKnowledgeEntry $entry) => ['id' => $entry->id, 'title' => $entry->title, 'text' => $entry->body, 'checked_by_staff' => $entry->is_manual || $entry->source_type === 'verified'])->values()->all(),
                'note'  => 'Answer only if these texts clearly answer the question, in two or three short sentences, in your own words and in the customer\'s language, with answer_kind shop_answer and the ids you used in sources. You may repeat a figure only exactly as a text states it. A text marked checked_by_staff is newer than our pages: when it disagrees with a page, follow it and cite it. Some texts are notes written for our staff: tell the customer only what answers their question, in plain words, and never repeat internal codes, ids, system or file names, what staff can do, or that a page of ours is wrong or out of date. If the texts do not answer the question, do not guess: answer with answer_kind offer_person and offer_reason not_sure.',
            ],
            'products' => [],
            'error'    => null,
            'sources'  => $entries->map(fn (ChatKnowledgeEntry $entry) => [
                'id'          => $entry->id,
                'kind'        => $entry->kind,
                'source_type' => $entry->source_type,
                'source_id'   => $entry->source_id,
                'title'       => $entry->title,
                'url'         => $entry->url,
                'body'        => $entry->body,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: null, sources: array<int, array{id: int, kind: string, source_type: string, source_id: string, title: string, url: null, body: string}>}
     */
    private function recentEmailsToCustomer(Customer $customer): array
    {
        $emails = Mailshot::query()
            ->whereIn('id', MailshotRecipient::query()->where('recipient_type', $customer->getMorphClass())->where('recipient_id', $customer->id)->select('mailshot_id'))
            ->whereIn('type', [MailshotTypeEnum::NEWSLETTER, MailshotTypeEnum::MARKETING])
            ->where('state', MailshotStateEnum::SENT)
            ->where('sent_at', '>=', now()->subDays(self::RECENT_EMAIL_DAYS))
            ->orderByDesc('sent_at')
            ->limit(self::RECENT_EMAIL_LIMIT)
            ->with('email.liveSnapshot')
            ->get()
            ->map(fn (Mailshot $mailshot) => [
                'id'      => -$mailshot->id,
                'mailshot' => $mailshot,
                'sent_on' => $mailshot->sent_at->format('j F Y'),
                'text'    => $this->emailText((string) $mailshot->email?->liveSnapshot?->compiled_layout),
            ])
            ->filter(fn (array $email) => $email['text'] !== '')
            ->values();

        if ($emails->isEmpty()) {
            return [
                'content'  => ['emails' => [], 'note' => 'We sent this customer no newsletter or marketing email in the last month. Say so in one short sentence with no number, with answer_kind question, and ask what they are looking for; for an older email, use answer_kind offer_person with offer_reason not_sure.'],
                'products' => [],
                'error'    => null,
                'sources'  => [],
            ];
        }

        return [
            'content'  => [
                'today'  => now()->format('j F Y'),
                'emails' => $emails->map(fn (array $email) => ['id' => $email['id'], 'subject' => $email['mailshot']->subject, 'sent_on' => $email['sent_on'], 'text' => $email['text']])->all(),
                'note'   => 'Answer only what the customer asks about these emails, in two or three short sentences, in your own words and in the customer\'s language, with answer_kind shop_answer and the ids you used in sources. Say when the email was sent, as a date written from sent_on, never how many days ago. You may repeat a figure, code or date only exactly as the email states it. An offer, code or deadline may have ended since: if the email\'s own dates show it has, say so; otherwise say it was in the email of that date and the product cards show today\'s prices. Ignore placeholders such as [Customer Name] and anything about unsubscribing. If the emails do not answer the question, use answer_kind offer_person with offer_reason not_sure.',
            ],
            'products' => [],
            'error'    => null,
            'sources'  => $emails->map(fn (array $email) => [
                'id'          => $email['id'],
                'kind'        => 'email',
                'source_type' => 'mailshot',
                'source_id'   => (string) $email['mailshot']->id,
                'title'       => $email['mailshot']->subject.' · '.$email['sent_on'],
                'url'         => null,
                'body'        => $email['sent_on'].' ('.$email['mailshot']->sent_at->format('j.n.Y, j. n.').")\n".$email['mailshot']->subject."\n".$email['text'],
            ])->all(),
        ];
    }

    private function emailText(string $html): string
    {
        $html = preg_replace('/<(style|script|head|title)\b[^>]*>.*?<\/\1>|<!--.*?-->/is', ' ', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>|<\/(p|div|h[1-6]|li|tr|table)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace(['/[^\S\n]+/u', '/\s*\n\s*/u'], [' ', "\n"], $text) ?? $text;

        return Str::limit(trim($text), self::RECENT_EMAIL_CHARACTERS);
    }

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string}
     */
    private function dispatchTimes(Customer $customer, string $locale): array
    {
        $days = GetShoppingAssistantDispatchDays::run($customer->shop);

        if ($days === null) {
            return ['content' => ['available' => false, 'note' => 'We have no recent dispatch figure for this shop. Say so and suggest asking customer service; do not guess.'], 'products' => [], 'error' => null];
        }

        return [
            'content'  => ['available' => true, 'note' => 'A sentence with the real dispatch figure is added under your reply. Write one short lead-in without any figure or delivery promise.'],
            'products' => [],
            'error'    => null,
            'fact'     => __('Over the last 30 days, 9 out of 10 orders were dispatched within :days days of being placed. This is dispatch from our warehouse, not delivery to you.', ['days' => $days], $locale),
        ];
    }

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string}
     */
    private function shippingCost(Customer $customer, string $locale): array
    {
        $order = Order::where('customer_id', $customer->id)->where('state', OrderStateEnum::CREATING)->latest('id')->first();
        $lines = $order ? Transaction::where('order_id', $order->id)->where('model_type', 'Product')->where('is_gift', false)->exists() : false;

        if (!$lines) {
            return ['content' => ['available' => false, 'note' => 'The basket is empty. Say that shipping is worked out in the basket once it has products, and offer to help choose some.'], 'products' => [], 'error' => null];
        }

        $fact = match (true) {
            (bool) $order->is_shipping_tbc                                       => __('Shipping for your current basket is still to be confirmed: the price is decided later, it is not free.', [], $locale),
            $order->shipping_engine?->value === 'auto' && (float) $order->shipping_amount > 0 => __('Shipping for your current basket is :amount before tax. It changes as you add or remove products.', ['amount' => Number::currency((float) $order->shipping_amount, $order->currency->code)], $locale),
            $order->shipping_engine?->value === 'auto'                           => __('Shipping for your current basket is free. It can change as you add or remove products.', [], $locale),
            default                                                              => null,
        };

        if ($fact === null) {
            return ['content' => ['available' => false, 'note' => 'Shipping for this basket is not worked out automatically. Say it is shown in the basket and at checkout; do not guess.'], 'products' => [], 'error' => null];
        }

        return [
            'content'  => ['available' => true, 'note' => 'A sentence with the real shipping cost of the current basket is added under your reply. Write one short lead-in without any amount.'],
            'products' => [],
            'error'    => null,
            'fact'     => $fact,
        ];
    }

    /**
     * The Gold Reward rule is the storefront's (HasGrData::getGrData): a member was invoiced within
     * the shop's interval, or has an extension; membership lasts until the later of the two. Member
     * prices are per-range volume offers and not every range has one (about 90% in most shops on
     * 12 Nov 2026, none in Aroma), so the sentences say "wherever a range has them", and a shop with
     * none says nothing about Gold Reward.
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact: string}
     */
    private function betterPriceOptions(Customer $customer, string $locale): array
    {
        $shop      = $customer->shop;
        $offers    = $shop->offers_data ?? [];
        $facts     = [];
        $date      = fn ($moment) => Carbon::parse($moment)->locale($locale)->isoFormat('LL') ?: Carbon::parse($moment)->locale('en')->isoFormat('LL');
        $hasGr     = Arr::get($offers, 'gr.active') && $this->shopHasMemberPrices($customer);
        $isMember  = false;

        if ($hasGr) {
            $interval    = (int) Arr::get($offers, 'gr.interval', 30);
            $label       = (string) (Arr::get($offers, 'gr.label') ?: __('Gold Reward', [], $locale));
            $memberUntil = collect([
                $customer->last_invoiced_at && $customer->last_invoiced_at->copy()->addDays($interval)->isFuture() ? $customer->last_invoiced_at->copy()->addDays($interval) : null,
                $customer->hasActiveGrExtension() ? $customer->gr_extended_until : null,
            ])->filter()->max();

            $isMember = $memberUntil !== null;
            $facts[]  = $isMember
                ? __('You are a :label member until :date, so you get member prices wherever a range has them. An order invoiced before then keeps your membership going.', ['label' => $label, 'date' => $date($memberUntil)], $locale)
                : __('Once an order of yours is invoiced you become a :label member for :days days, with member prices wherever a range has them. Until then, a range is at member price when you order enough of it at once.', ['label' => $label, 'days' => $interval], $locale);

            if (Arr::get($offers, 'gr.amnesty') && Arr::get($offers, 'gr.amnesty_until') && Carbon::parse(Arr::get($offers, 'gr.amnesty_until'))->isFuture()) {
                $facts[] = __('Until :date everyone gets member prices.', ['date' => $date(Arr::get($offers, 'gr.amnesty_until'))], $locale);
            }
        }

        $offersCalls = StoreShoppingAssistantSalesLead::offersCalls($customer->organisation, GetAikuAssistant::run($customer->group_id));
        if ($offersCalls) {
            $threshold = StoreShoppingAssistantSalesLead::make()->threshold($customer->organisation, $shop->currency);
            $facts[]   = __('For an order of :amount or more, a member of our team can call you to talk about it and see whether better terms are possible.', ['amount' => Number::currency($threshold, $shop->currency->code, $locale, 0)], $locale);
        }

        return [
            'content'  => [
                'gold_reward'  => $hasGr ? ($isMember ? 'member' : 'not_member') : 'not_offered',
                'calls_offered' => $offersCalls,
                'note'         => $offersCalls
                    ? 'Sentences with the details are added under your reply: never repeat their figures or dates. In one or two sentences: say you cannot change prices yourself, offer lower-priced options of what they asked for (search_products with sort cheapest), and ask how much they plan to order. Never promise a discount.'
                    : 'Sentences with the details are added under your reply: never repeat their figures or dates. In one or two sentences: say you cannot change prices yourself and offer lower-priced options of what they asked for (search_products with sort cheapest). Calls from our team are not offered in this shop: do not offer one or ask how much they plan to order. Never promise a discount.',
            ],
            'products' => [],
            'error'    => null,
            'fact'     => implode(' ', $facts),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string}
     */
    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string}
     */
    private function reportToEngineers(ShoppingAssistantConversation $conversation, array $arguments): array
    {
        $summary = is_string($arguments['summary'] ?? null) ? trim($arguments['summary']) : '';
        $details = is_string($arguments['details'] ?? null) ? trim($arguments['details']) : '';
        $kind    = in_array($arguments['kind'] ?? null, self::ENGINEER_TICKET_KINDS, true) ? TicketKindEnum::from($arguments['kind']) : null;

        if ($summary === '' || $details === '' || !$kind) {
            return $this->error('summary, details and kind (one of '.implode(', ', self::ENGINEER_TICKET_KINDS).') are required.');
        }

        $whyOnlyUs = is_string($arguments['why_only_us'] ?? null) ? mb_substr(trim($arguments['why_only_us']), 0, 500) : '';
        $result    = StoreShoppingAssistantEngineerTicket::run($conversation, $summary, mb_substr($details, 0, 2000), $kind, $whyOnlyUs, ($arguments['tried_fix'] ?? false) === true);

        if (!$result['ticket']) {
            return [
                'content'  => ['reported' => false, 'note' => 'Not raised: it is not certain this is a problem only our engineers can fix. If the customer has not tried the fix from shop_information, give it; otherwise answer with answer_kind offer_person and offer_reason not_sure so a person in customer services helps them. Never mention tickets or engineers.'],
                'products' => [],
                'error'    => null,
            ];
        }

        $reference = $result['ticket']->reference;
        $canFollow = $conversation->web_user_id && self::isDropshipping($conversation->customer);
        $locale    = $conversation->language_code;

        $fact = match (true) {
            $result['status'] === 'already_reported' => __('I have added this to :reference, which our engineers are already looking into.', ['reference' => $reference], $locale),
            $canFollow                                => __('I have passed this to our engineers as :reference. You can follow it under Tickets in your account.', ['reference' => $reference], $locale),
            default                                   => __('I have passed this to our engineers as :reference.', ['reference' => $reference], $locale),
        };

        return [
            'content' => ['reported' => true, 'already_open' => $result['status'] === 'already_reported', 'note' => 'The ticket reference is added under your reply. Say in one or two sentences that our engineers will look into it, without promising a time or a fix, and offer to help with anything else meanwhile.'],
            'products' => [],
            'error'    => null,
            'fact'     => $fact,
        ];
    }

    private function askOurTeam(ShoppingAssistantConversation $conversation, array $arguments): array
    {
        $question = is_string($arguments['question'] ?? null) ? trim($arguments['question']) : '';
        if ($question === '') {
            return $this->error('question must be the customer\'s question in one English sentence.');
        }

        $words  = (string) $conversation->messages()->where('role', 'customer')->latest('id')->value('body');
        $result = StoreShoppingAssistantTeamQuestion::run($conversation, $question, $words ?: $question);

        $note = match (true) {
            $result['status'] === 'off', $result['status'] === 'limit' => 'Our team cannot be asked now: say you are not sure and offer a person (answer_kind offer_person, offer_reason not_sure).',
            $result['live']                                            => 'Passed on to a person. Say honestly, in one or two sentences, that you do not know and have asked one of our experts; if someone is free in the next few minutes their answer will appear here, otherwise we will get back to them as soon as we can. Never promise an answer or a time, and never guess the answer.',
            default                                                    => 'Passed on to a person. Say honestly, in one sentence, that you do not know and have passed it to one of our experts, who will get back to them as soon as they can. Never promise an answer or a time, and never guess the answer.',
        };

        if (in_array($result['status'], ['off', 'limit'], true)) {
            return ['content' => ['passed_on' => false, 'note' => $note], 'products' => [], 'error' => 'not passed on'];
        }

        return ['content' => ['passed_on' => true, 'answer_may_come_here' => $result['live'], 'note' => $note], 'products' => [], 'error' => null];
    }

    /**
     * The documents and compliance details the website shows on a product page, for the product whose
     * code the customer typed, else the closest by meaning (in stock or not): only public scopes, the
     * same rule as the product page (HasWebBlockProductAttachments), and ingredients and CPNP only for
     * a product of one trade unit. Links and the CPNP number and ingredients, which hold digits, go
     * under the reply as written here; the model gets only which documents exist.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string, documents?: array<int, array{label: string, media: string}>, names?: array<int, string>}
     */
    private function productDocuments(Customer $customer, array $arguments, string $locale): array
    {
        $query = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        if ($query === '') {
            return $this->error('query must be the product the customer asked about.');
        }

        $byCode  = $this->productsByCode($customer, $query);
        $matches = $byCode->isNotEmpty() ? $byCode : SearchCatalogueByMeaning::run($customer->shop, $query, $customer, 3, false);
        /** @var ?Product $product */
        $product = $matches->first();

        if (!$product) {
            return ['content' => ['product' => null, 'note' => 'No product matches. Ask which product they mean.'], 'products' => [], 'error' => null];
        }

        $documents = DB::table('model_has_attachments')
            ->join('media', 'media.id', '=', 'model_has_attachments.media_id')
            ->where('model_has_attachments.model_type', 'Product')
            ->where('model_has_attachments.model_id', $product->id)
            ->whereIn('model_has_attachments.scope', TradeAttachmentScopeEnum::publicScopes())
            ->orderBy('model_has_attachments.scope')
            ->get(['model_has_attachments.scope', 'media.ulid'])
            ->map(fn ($document) => ['label' => __(TradeAttachmentScopeEnum::labels()[$document->scope] ?? $document->scope, [], $locale).' · '.$product->name, 'media' => $document->ulid, 'kind' => TradeAttachmentScopeEnum::labels()[$document->scope] ?? $document->scope])
            ->values();

        $singleTradeUnit = $product->is_single_trade_unit || $product->tradeUnits()->count() <= 1;
        $details         = array_filter([
            $singleTradeUnit && filled($product->cpnp_number) ? __('CPNP Number', [], $locale).': '.$product->cpnp_number : null,
            $singleTradeUnit && filled($product->marketing_ingredients) ? __('Ingredients', [], $locale).': '.trim(strip_tags((string) $product->marketing_ingredients)) : null,
        ]);
        $countries = array_values(array_filter(array_map(fn (string $code) => NaturalLanguage::make()->country(trim($code))['name'] ?? null, explode(',', (string) $product->country_of_origin))));

        $result = [
            'content'   => [
                'product'              => $product->name,
                'exact_code_match'     => $byCode->isNotEmpty(),
                'documents'            => $documents->pluck('kind')->unique()->values()->all(),
                'has_cpnp_number'      => $singleTradeUnit && filled($product->cpnp_number),
                'has_ingredients'      => $singleTradeUnit && filled($product->marketing_ingredients),
                'countries_of_origin'  => $countries,
                'other_close_products' => $matches->slice(1)->pluck('name')->values()->all(),
                'note'                 => $documents->isEmpty() && !$details
                    ? 'We have no public documents for this product online. Say so by its name, and offer a person (answer_kind offer_person, offer_reason not_sure) if they need a document.'
                    : 'Say by the product name which of these we have: the download links and the CPNP number and ingredients are added under your reply, so never write a link or a number. Each product page lists its documents too. If the product may not be the one they meant, name other_close_products and ask.',
            ],
            'products'  => [],
            'error'     => null,
            'documents' => $documents->map(fn (array $document) => ['label' => $document['label'], 'media' => $document['media']])->all(),
            'names'     => [$product->name],
        ];
        if ($details) {
            $result['fact'] = $product->name.' – '.implode('. ', $details);
        }

        return $result;
    }

    /**
     * Products whose best discount on the card (products.offers_data best_percentage_off, what the
     * storefront shows) is a sale running now: a category-offers campaign, never the volume / Gold
     * Reward member prices. Optionally only one kind of product, matched by meaning. Sale names are
     * internal labels with percentages ("50BF26", "Black Friday - 50% RABATT"), so the model gets none.
     *
     * ponytail: the first `limit` by family; rank by sales when shops run more sales than fit one answer.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function productsOnOffer(Customer $customer, array $arguments, string $locale): array
    {
        $kind   = $this->kindOf($arguments);
        $onSale = $this->recommendableProducts($customer->shop, $customer)
            ->join('offers', 'offers.id', '=', DB::raw("(products.offers_data::jsonb->'best_percentage_off'->>'offer_id')::bigint"))
            ->join('offer_campaigns', 'offer_campaigns.id', '=', 'offers.offer_campaign_id')
            ->where('offer_campaigns.type', 'category-offers')
            ->where('offers.state', 'active')
            ->where('offers.status', true)
            ->where(fn ($query) => $query->whereNull('offers.end_at')->orWhere('offers.end_at', '>', now()))
            ->whereRaw("(products.offers_data::jsonb->'best_percentage_off'->>'percentage_off')::numeric > 0")
            ->select('products.*');

        if ($kind !== '') {
            $ofKind = $this->productIdsOfKind($customer, $kind);
            if ($ofKind === null) {
                return $this->error('The search is not available right now, try again.');
            }
            $onSale->whereIn('products.id', $ofKind);
        }

        $limit    = $this->limit($arguments);
        $products = $onSale->orderBy('products.family_id')->orderBy('products.id')->limit($limit + 1)->get();
        $result   = $this->productsResult($customer, $products->take($limit), fn () => 'matches_request', $locale);

        $result['content']['note'] = match (true) {
            $products->isEmpty()          => 'Nothing'.($kind !== '' ? ' of that kind' : '').' is on sale right now. Say so plainly and offer to show the products they asked for with search_products.',
            $products->count() > $limit   => 'These are on sale now, a selection of more: say so. Call it "our sale" or "on offer now", never a percentage; the discount shows on each product card.',
            default                       => 'These are all the products on sale now. Call it "our sale" or "on offer now", never a percentage; the discount shows on each product card.',
        };

        return $result;
    }

    /**
     * The out of stock products the customer most likely means: the one whose code they typed, else
     * the closest matches by meaning that cannot be ordered now.
     *
     * @return Collection<int, Product>
     */
    private function outOfStockMatches(Customer $customer, string $query): Collection
    {
        if ($query === '') {
            return collect();
        }

        return $this->productsByCode($customer, $query)
            ->concat(SearchCatalogueByMeaning::run($customer->shop, $query, $customer, 6, false))
            ->filter(fn (Product $product) => $product->available_quantity <= 0)
            ->unique('id')
            ->take(self::MAX_OUT_OF_STOCK)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string, names?: array<int, string>}
     */
    private function restockInformation(Customer $customer, array $arguments, string $locale): array
    {
        $query    = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $products = (new EloquentCollection($this->outOfStockMatches($customer, $query)->all()))->loadMissing('family');
        $followUp = StoreShoppingAssistantRestockQuestion::isOn($customer->organisation) && StoreShoppingAssistantRestockQuestion::recipientsOf($customer->organisation)->isNotEmpty();
        $date     = fn ($moment) => $moment->locale($locale)->isoFormat('LL') ?: $moment->locale('en')->isoFormat('LL');
        $facts    = [];
        $items    = [];

        foreach ($products as $product) {
            $estimate = GetProductRestockEstimate::run($product);
            if ($estimate['status'] === 'expected') {
                $expected = $estimate['date'];
                [$from, $to] = $expected->day <= 15 ? [$expected->startOfMonth(), $expected->setDay(15)] : [$expected->setDay(16), $expected->endOfMonth()];
                $facts[] = __('The :product is on order and expected between :from and :to.', ['product' => $product->name, 'from' => $date($from), 'to' => $date($to)], $locale);
            }
            $items[] = ['name' => $product->name, 'family' => $product->family?->name, 'status' => $estimate['status'] === 'expected' ? 'expected' : 'no_reliable_date'];
        }

        $result = [
            'content'  => [
                'out_of_stock_products'    => $items,
                'purchasing_follow_up'     => $followUp,
                'note'                     => match (true) {
                    $items === []                                                   => 'No out of stock product matches. If they named a product, it may be in stock: check with search_products.',
                    $followUp                                                       => 'Sentences with the expected periods are added under your reply: never repeat their dates. For a product with no_reliable_date, say we do not have a reliable date and ask whether they would like our purchasing team to get back to them by email; call ask_purchasing_team only after they say yes. Never guess a date.',
                    default                                                         => 'Sentences with the expected periods are added under your reply: never repeat their dates. For a product with no_reliable_date, say we do not have a reliable date and suggest similar products in stock. Never guess a date.',
                },
            ],
            'products' => [],
            'error'    => null,
            'names'    => $products->flatMap(fn (Product $product) => [$product->name, $product->family?->name])->filter()->values()->all(),
        ];
        if ($facts) {
            $result['fact'] = implode(' ', $facts);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string}
     */
    private function askPurchasingTeam(ShoppingAssistantConversation $conversation, Customer $customer, array $arguments): array
    {
        if (!filter_var($arguments['customer_agreed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->error('Ask the customer first whether they want an answer from our purchasing team; call this only after they say yes.');
        }

        $question = is_string($arguments['question'] ?? null) ? trim($arguments['question']) : '';
        if ($question === '') {
            return $this->error('question must be the customer\'s question in one sentence.');
        }

        $query   = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';
        $product = $this->outOfStockMatches($customer, $query)->first();
        $result  = StoreShoppingAssistantRestockQuestion::run($conversation, $product, $question);

        return ['content' => ['passed_on' => $result['status'] !== 'off', 'note' => $result['status'] === 'off'
            ? 'Our purchasing team does not take these questions in this shop: say we have no reliable date and suggest similar products in stock.'
            : 'Passed on. Say in one sentence that our purchasing team will reply by email, without promising when or what they will say.'], 'products' => [], 'error' => null];
    }

    private function shopHasMemberPrices(Customer $customer): bool
    {
        return DB::table('offers')
            ->join('offer_campaigns', 'offer_campaigns.id', '=', 'offers.offer_campaign_id')
            ->where('offers.shop_id', $customer->shop_id)
            ->where('offer_campaigns.type', 'volume-discount')
            ->where('offers.status', true)
            ->where('offers.state', 'active')
            ->where('offers.trigger_type', 'ProductCategory')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $arguments
     *
     * @return array{content: array<string, mixed>, products: array{}, error: ?string}
     */
    private function bigOrderLead(ShoppingAssistantConversation $conversation, array $arguments): array
    {
        if (!is_numeric($arguments['planned_amount'] ?? null) || (float) $arguments['planned_amount'] <= 0) {
            return $this->error('planned_amount must be a positive number.');
        }

        $agreed = filter_var($arguments['customer_agreed_to_call'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $text   = fn (string $key) => is_string($arguments[$key] ?? null) ? $arguments[$key] : null;
        $result = StoreShoppingAssistantSalesLead::run($conversation, (float) $arguments['planned_amount'], $text('currency'), $agreed, $text('phone'), $text('best_time'), $text('details'));

        $note = match (true) {
            $result['status'] === 'not_offered'     => 'Calls from our team are not offered in this shop: do not offer one or say the order was passed on. Offer lower-priced options and what better_price_options says.',
            $result['status'] === 'below_threshold' => 'Below the order size for a call: do not offer one. Offer lower-priced options and what better_price_options says.',
            !$agreed                                => 'Passed on to our team. Ask in one short sentence whether they would like a call from our team to talk about better terms, on the phone number in their account or another one, and when suits them. Call big_order_lead again with their answer.',
            default                                 => 'Passed on to our team. Say in one sentence that a member of our team will call them, without naming anyone, giving a time we did not agree, or promising a discount.',
        };

        return ['content' => ['qualifies' => in_array($result['status'], ['created', 'updated'], true), 'note' => $note], 'products' => [], 'error' => null];
    }

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, cleared: bool}
     */
    private function clearConversation(ShoppingAssistantConversation $conversation): array
    {
        $conversation->update(['status' => ShoppingAssistantConversationStatusEnum::CLEARED]);

        return ['content' => ['cleared' => true], 'products' => [], 'error' => null, 'cleared' => true];
    }

    /**
     * @param  callable(Product): string  $reasonCodeOf
     *
     * @return array{content: array<string, mixed>, products: array<int, array{id: int, reason_code: string, reason: ?string}>, error: ?string}
     */
    private function productsResult(Customer $customer, Collection $products, callable $reasonCodeOf, string $locale, bool $withLastOrdered = false): array
    {
        $products = (new EloquentCollection($products->all()))->loadMissing('family');
        $seen    = [];
        $content = [];
        $found   = [];
        $listed  = self::isDropshipping($customer) && $products->isNotEmpty() ? array_flip(GetCustomerRangeGaps::listedProductIds($customer)) : [];

        foreach ($products as $product) {
            if (isset($seen[$product->id])) {
                continue;
            }
            $seen[$product->id] = true;
            $reasonCode         = $reasonCodeOf($product);

            $item = [
                'id'     => $product->id,
                'name'   => $product->name,
                'family'    => $product->family?->name,
                'family_id' => $product->family_id,
                'why'       => $this->reasonText($reasonCode, $customer, 'en'),
            ];
            if ($withLastOrdered) {
                $item['last_ordered'] = $product->customer_last_ordered_at?->format('Y-m');
            }
            if (isset($listed[$product->id])) {
                $item['already_listed'] = true;
            }

            $content[] = $item;
            $found[]   = ['id' => $product->id, 'reason_code' => $reasonCode, 'reason' => $this->reasonText($reasonCode, $customer, $locale)];
        }

        $result = ['content' => ['products' => $content], 'products' => $found, 'error' => null];
        if ($listed && collect($content)->contains('already_listed', true)) {
            $result['content']['already_listed_note'] = 'Products marked already_listed are already in the customer\'s stores: never present them as new or as something to add; leave them out unless the customer asked for that product.';
        }

        return $result;
    }

    public function segmentReasonCode(Product $product): string
    {
        return self::SEGMENT_REASON_CODES[$product->segment_level] ?? 'best_seller';
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function limit(array $arguments): int
    {
        $limit = $arguments['limit'] ?? null;

        return is_numeric($limit) ? max(1, min(self::MAX_LIMIT, (int) $limit)) : self::DEFAULT_LIMIT;
    }

    /**
     * @return array{content: array{error: string}, products: array{}, error: string}
     */
    private function error(string $message): array
    {
        return ['content' => ['error' => $message], 'products' => [], 'error' => $message];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  array<int, string>  $required
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        $parameters = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
        if ($required) {
            $parameters['required'] = $required;
        }

        return ['type' => 'function', 'function' => ['name' => $name, 'description' => $description, 'parameters' => $parameters]];
    }

    public static function isDropshipping(Customer $customer): bool
    {
        return $customer->shop->type === ShopTypeEnum::DROPSHIPPING;
    }
}
