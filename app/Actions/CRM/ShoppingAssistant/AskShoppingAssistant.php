<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\CRM\RangeAdvisor\WithRecommendableProducts;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEventTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * One customer turn of the shopping assistant: the model calls our tools to find real products and
 * answers through present_products. The rules below are enforced here, never left to the prompt,
 * and each one that fires is recorded in the assistant message's flags:
 *
 * - dropped_unknown_product: a presented id that no tool returned in this turn and that was not
 *   shown earlier in this conversation is dropped. A product sits in one group only, at most 4 per group.
 * - dropped_unavailable_product: every presented id is checked again at answer time and dropped
 *   when it is no longer live, in stock, priced and visible to the customer.
 * - reply_replaced_number: prices, discounts and stock never come from the AI. The reply may only
 *   contain numbers (digits with an optional decimal part) that the customer wrote in this
 *   conversation, or a size written exactly as in the name of a product found or shown in it
 *   ("190g", "50mm"); otherwise the whole reply becomes the fixed fallback. A currency sign on its own is allowed:
 *   "£600" after the customer wrote "600 pounds" is an echo, and a price is caught by its number.
 *   Follow-ups breaking the same rule are all dropped.
 * - model_unavailable: an HTTP failure, an empty or unreadable answer, or a content filter stop
 *   gives the fallback with no products. The customer message is stored first, so it is never lost.
 * - model_call_limit: no final answer within services.shopping_assistant.max_model_calls calls.
 *
 * Replayed to the model: the system prompt, the last 12 stored messages of the conversation (the
 * assistant's with the names and ids of the products shown with it) and the new customer message.
 * Earlier tool results are not replayed. The customer's contact name and email are never sent.
 *
 * ponytail: no streaming, the turn is one blocking request; stream when it is reachable from the website.
 * ponytail: replay capped at 12 messages; summarise older turns if conversations grow long.
 * ponytail: the reply language is the shop's and is named in the prompt but not checked; add a
 * language check on the reply if the model drifts.
 * ponytail: a product name with digits in the reply triggers the number fallback, and numbers spelt
 * out in words pass; tighten when the flags show either happening.
 */
class AskShoppingAssistant
{
    use AsAction;
    use WithAIGateway;
    use WithRecommendableProducts;

    public string $commandSignature = 'shopping-assistant:ask {customer : customer id} {message : what the customer says} {--conversation= : ulid of a conversation to continue}';

    public string $commandDescription = 'Ask the shopping assistant a question as a customer and print its answer, the products it picked and what the turn cost';

    public const int MAX_MESSAGE = 2000;

    public const int REPLAY_MESSAGES = 12;

    public const int MAX_FOLLOW_UP = 80;

    public const array GROUPS = ['must_haves', 'add_ons'];

    private const string NUMBER_PATTERN = '/\d+(?:[.,]\d+)?/';

    /**
     * @return array{message: ShoppingAssistantMessage, reply: string, groups: array<string, Collection<int, Product>>, follow_ups: array<int, string>}
     */
    public function handle(ShoppingAssistantConversation $conversation, string $message): array
    {
        $startedAt = hrtime(true);
        $message   = mb_substr(trim($message), 0, self::MAX_MESSAGE);
        $customer  = $conversation->customer;
        $model     = config('services.shopping_assistant.model');

        $customerMessage = $conversation->messages()->create([
            'role' => ShoppingAssistantMessageRoleEnum::CUSTOMER,
            'body' => $message,
        ]);
        $conversation->increment('messages_count', 1, ['last_message_at' => now()]);

        $turn = $this->runModel($conversation, $customer, $customerMessage, $message, $model);

        $flags          = $turn['flags'];
        $knownProductIds = $conversation->events()->where('type', ShoppingAssistantEventTypeEnum::SHOWN)->pluck('product_id')->merge(array_keys($turn['found']))->unique();
        $productNames    = Product::whereIn('id', $knownProductIds)->pluck('name')->implode("\n");
        $customerText    = $conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->pluck('body')->implode("\n");
        $groups         = ['must_haves' => collect(), 'add_ons' => collect()];
        $reply          = '';
        $followUps      = [];

        if ($turn['answer'] !== null) {
            $reply = trim((string) ($turn['answer']['reply'] ?? ''));
            if ($reply === '') {
                $flags[] = 'model_unavailable';
            } else {
                $groups    = $this->presentedGroups($conversation, $customer, $turn['answer'], $turn['found'], $flags);
                $followUps = $this->followUps($turn['answer']['follow_ups'] ?? [], $customerText, $productNames);
                if (!$this->onlyGivenNumbers($reply, $customerText, $productNames)) {
                    $flags[] = 'reply_replaced_number';
                    $reply   = '';
                }
            }
        }

        $hasProducts = collect($groups)->contains(fn (Collection $products) => $products->isNotEmpty());
        if ($reply === '') {
            $reply = $hasProducts
                ? __('Here are some ideas for your shop.', [], $conversation->language_code)
                : __('Sorry, I could not find an answer just now. Please try again.', [], $conversation->language_code);
        }

        $latency = (int) round((hrtime(true) - $startedAt) / 1e6);

        $assistantMessage = DB::transaction(function () use ($conversation, $reply, $turn, $followUps, $flags, $model, $latency, $groups) {
            $assistantMessage = $conversation->messages()->create([
                'role'              => ShoppingAssistantMessageRoleEnum::ASSISTANT,
                'body'              => $reply,
                'tool_calls'        => $turn['tool_calls'] ?: null,
                'follow_ups'        => $followUps ?: null,
                'flags'             => $flags ? array_values(array_unique($flags)) : null,
                'model'             => $model,
                'model_calls'       => $turn['model_calls'],
                'prompt_tokens'     => $turn['prompt_tokens'],
                'completion_tokens' => $turn['completion_tokens'],
                'cost'              => $turn['cost'],
                'latency_ms'        => $latency,
            ]);

            foreach ($groups as $group => $products) {
                foreach ($products->values() as $index => $product) {
                    $conversation->events()->create([
                        'shopping_assistant_message_id' => $assistantMessage->id,
                        'type'                          => ShoppingAssistantEventTypeEnum::SHOWN,
                        'product_id'                    => $product->id,
                        'group'                         => $group,
                        'position'                      => $index + 1,
                        'reason_code'                   => $product->assistant_reason_code,
                        'reason'                        => $product->assistant_reason,
                    ]);
                }
            }

            $conversation->update([
                'messages_count'    => $conversation->messages_count + 1,
                'model_calls'       => $conversation->model_calls + $turn['model_calls'],
                'prompt_tokens'     => $conversation->prompt_tokens + $turn['prompt_tokens'],
                'completion_tokens' => $conversation->completion_tokens + $turn['completion_tokens'],
                'cost'              => (float) $conversation->cost + $turn['cost'],
                'model'             => $model,
                'last_message_at'   => now(),
            ]);

            return $assistantMessage;
        });

        return [
            'message'    => $assistantMessage,
            'reply'      => $reply,
            'groups'     => $groups,
            'follow_ups' => $followUps,
        ];
    }

    /**
     * The tool loop. Model calls run outside any transaction, and a failure on a later call keeps
     * the tokens, cost and tool log of the earlier ones.
     *
     * @return array{answer: ?array<string, mixed>, found: array<int, array{id: int, reason_code: string, reason: ?string}>, tool_calls: array<int, array{name: string, arguments: mixed, product_ids: array<int>, error: ?string, ms: int}>, flags: array<int, string>, model_calls: int, prompt_tokens: int, completion_tokens: int, cost: float}
     */
    private function runModel(ShoppingAssistantConversation $conversation, ?Customer $customer, ShoppingAssistantMessage $customerMessage, string $message, string $model): array
    {
        $turn = ['answer' => null, 'found' => [], 'tool_calls' => [], 'flags' => [], 'model_calls' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost' => 0.0];

        if (!$customer) {
            $turn['flags'][] = 'model_unavailable';

            return $turn;
        }

        $tools    = GetShoppingAssistantTools::make();
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($conversation, $customer)]],
            $this->replay($conversation, $customerMessage),
            [['role' => 'user', 'content' => $message]]
        );

        for ($call = 0; $call < (int) config('services.shopping_assistant.max_model_calls'); $call++) {
            $response = $this->callModel($model, $messages, $tools->definitions());

            if ($response === null) {
                $turn['flags'][] = 'model_unavailable';

                return $turn;
            }

            $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
            $turn['model_calls']++;
            $turn['prompt_tokens']     += (int) ($usage['prompt_tokens'] ?? 0);
            $turn['completion_tokens'] += (int) ($usage['completion_tokens'] ?? 0);
            $turn['cost']              += (float) ($this->aiUsageCost($usage) ?? 0);

            $choice    = $response['choices'][0] ?? null;
            $assistant = is_array($choice) ? ($choice['message'] ?? null) : null;

            if (!is_array($assistant) || ($choice['finish_reason'] ?? null) === 'content_filter') {
                $turn['flags'][] = 'model_unavailable';

                return $turn;
            }

            $toolCalls = array_values(array_filter(is_array($assistant['tool_calls'] ?? null) ? $assistant['tool_calls'] : [], 'is_array'));
            $present   = collect($toolCalls)->first(fn (array $toolCall) => data_get($toolCall, 'function.name') === GetShoppingAssistantTools::PRESENT_PRODUCTS);

            if ($present) {
                $answer               = $this->decodeArguments(data_get($present, 'function.arguments'));
                $turn['tool_calls'][] = [
                    'name'        => GetShoppingAssistantTools::PRESENT_PRODUCTS,
                    'arguments'   => $answer ?? data_get($present, 'function.arguments'),
                    'product_ids' => [],
                    'error'       => $answer === null ? 'invalid_arguments' : null,
                    'ms'          => 0,
                ];
                if ($answer === null) {
                    $turn['flags'][] = 'model_unavailable';
                } else {
                    $turn['answer'] = $answer;
                }

                return $turn;
            }

            if (!$toolCalls) {
                $turn['answer'] = ['reply' => is_string($assistant['content'] ?? null) ? $assistant['content'] : ''];

                return $turn;
            }

            $messages[] = $assistant;

            foreach ($toolCalls as $toolCall) {
                $name      = (string) data_get($toolCall, 'function.name', '');
                $arguments = $this->decodeArguments(data_get($toolCall, 'function.arguments'));
                $toolStart = hrtime(true);

                $result = $arguments === null
                    ? ['content' => ['error' => 'The arguments were not a JSON object.'], 'products' => [], 'error' => 'invalid_arguments']
                    : $tools->execute($conversation, $name, $arguments);

                foreach ($result['products'] as $product) {
                    $turn['found'][$product['id']] ??= $product;
                }

                $turn['tool_calls'][] = [
                    'name'        => $name,
                    'arguments'   => $arguments ?? data_get($toolCall, 'function.arguments'),
                    'product_ids' => array_column($result['products'], 'id'),
                    'error'       => $result['error'],
                    'ms'          => (int) round((hrtime(true) - $toolStart) / 1e6),
                ];

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content'      => json_encode($result['content'], JSON_UNESCAPED_UNICODE),
                ];
            }
        }

        $turn['flags'][] = 'model_call_limit';

        return $turn;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     *
     * @return ?array<string, mixed>
     */
    private function callModel(string $model, array $messages, array $tools): ?array
    {
        try {
            $response = $this->aiRequest()
                ->connectTimeout(10)
                ->timeout((int) config('services.shopping_assistant.timeout'))
                ->post('chat/completions', [
                    'model'       => $this->aiModel($model),
                    'messages'    => $messages,
                    'tools'       => $tools,
                    'tool_choice' => 'auto',
                    'max_tokens'  => 4000,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Shopping assistant model call failed: '.$exception->getMessage());

            return null;
        }

        if (!$response->successful()) {
            Log::warning('Shopping assistant model call failed: '.$response->status().' '.Str::limit($response->body(), 500));

            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function decodeArguments(mixed $arguments): ?array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        if (!is_string($arguments)) {
            return null;
        }

        $decoded = json_decode($arguments === '' ? '{}' : $arguments, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  array<int, array{id: int, reason_code: string, reason: ?string}>  $found
     * @param  array<int, string>  $flags
     *
     * @return array<string, Collection<int, Product>>
     */
    private function presentedGroups(ShoppingAssistantConversation $conversation, Customer $customer, array $answer, array $found, array &$flags): array
    {
        $allowed = $found;
        $conversation->events()
            ->where('type', ShoppingAssistantEventTypeEnum::SHOWN)
            ->orderBy('id')
            ->get(['product_id', 'reason_code', 'reason'])
            ->each(function ($event) use (&$allowed) {
                $allowed[$event->product_id] ??= ['id' => $event->product_id, 'reason_code' => (string) $event->reason_code, 'reason' => $event->reason];
            });

        $picked = array_fill_keys(self::GROUPS, []);
        $used   = [];

        foreach (self::GROUPS as $group) {
            foreach (is_array($answer[$group] ?? null) ? $answer[$group] : [] as $id) {
                if (!is_numeric($id) || !isset($allowed[(int) $id])) {
                    $flags[] = 'dropped_unknown_product';
                    continue;
                }
                $id = (int) $id;
                if (isset($used[$id]) || count($picked[$group]) >= GetShoppingAssistantTools::MAX_PER_GROUP) {
                    continue;
                }
                $used[$id]        = true;
                $picked[$group][] = $id;
            }
        }

        $available = $used
            ? $this->recommendableProducts($customer->shop, $customer)->select('products.*')->whereIn('products.id', array_keys($used))->get()->keyBy('id')
            : collect();

        $groups = [];
        foreach ($picked as $group => $ids) {
            $groups[$group] = collect();
            foreach ($ids as $id) {
                $product = $available->get($id);
                if (!$product) {
                    $flags[] = 'dropped_unavailable_product';
                    continue;
                }
                $product->setAttribute('assistant_reason_code', $allowed[$id]['reason_code']);
                $product->setAttribute('assistant_reason', $allowed[$id]['reason'] ?? GetShoppingAssistantTools::make()->reasonText($allowed[$id]['reason_code'], $customer, $conversation->language_code));
                $groups[$group]->push($product);
            }
        }

        return $groups;
    }

    /**
     * @return array<int, string>
     */
    private function followUps(mixed $followUps, string $customerText, string $productNames): array
    {
        $followUps = collect(is_array($followUps) ? $followUps : [])
            ->filter(fn ($followUp) => is_string($followUp) && trim($followUp) !== '')
            ->map(fn (string $followUp) => mb_substr(trim($followUp), 0, self::MAX_FOLLOW_UP))
            ->take(GetShoppingAssistantTools::MAX_FOLLOW_UPS)
            ->values();

        if ($followUps->contains(fn (string $followUp) => !$this->onlyGivenNumbers($followUp, $customerText, $productNames))) {
            return [];
        }

        return $followUps->all();
    }

    /**
     * A size written as in a product's name ("190g", "50mm", "10 ml") is taken out of the text first;
     * every number left must be one the customer wrote, so "£10" or "120 shops" never passes on the
     * strength of "10 ml" or "120g" in a name.
     */
    public function onlyGivenNumbers(string $text, string $customerText, string $productNames = ''): bool
    {
        preg_match_all('/\d+(?:[.,]\d+)?\s?\p{L}+/u', $productNames, $sizes);
        foreach (array_unique($sizes[0]) as $size) {
            $text = preg_replace('/(?<![\d.,£€$])'.preg_quote($size, '/').'(?![\p{L}\d])/iu', ' ', $text);
        }

        $given = $this->numbers($customerText);

        foreach ($this->numbers($text) as $number) {
            if (!in_array($number, $given, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function numbers(string $text): array
    {
        preg_match_all(self::NUMBER_PATTERN, $text, $matches);

        return array_map(fn (string $number) => str_replace(',', '.', $number), $matches[0]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function replay(ShoppingAssistantConversation $conversation, ShoppingAssistantMessage $customerMessage): array
    {
        $earlier = $conversation->messages()
            ->where('id', '<', $customerMessage->id)
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(self::REPLAY_MESSAGES)
            ->get()
            ->reverse()
            ->skipWhile(fn (ShoppingAssistantMessage $earlierMessage) => $earlierMessage->role !== ShoppingAssistantMessageRoleEnum::CUSTOMER);

        $shown = DB::table('shopping_assistant_events')
            ->join('products', 'products.id', '=', 'shopping_assistant_events.product_id')
            ->whereIn('shopping_assistant_events.shopping_assistant_message_id', $earlier->pluck('id'))
            ->where('shopping_assistant_events.type', ShoppingAssistantEventTypeEnum::SHOWN->value)
            ->orderBy('shopping_assistant_events.id')
            ->get(['shopping_assistant_events.shopping_assistant_message_id', 'products.id', 'products.name'])
            ->groupBy('shopping_assistant_message_id');

        return $earlier->map(function (ShoppingAssistantMessage $earlierMessage) use ($shown) {
            if ($earlierMessage->role === ShoppingAssistantMessageRoleEnum::CUSTOMER) {
                return ['role' => 'user', 'content' => $earlierMessage->body];
            }

            $content = $earlierMessage->body;
            if ($products = $shown->get($earlierMessage->id)) {
                $content .= "\nShown: ".$products->map(fn ($product) => "$product->name (id $product->id)")->implode('; ');
            }

            return ['role' => 'assistant', 'content' => $content];
        })->values()->all();
    }

    private function systemPrompt(ShoppingAssistantConversation $conversation, Customer $customer): string
    {
        $shopName     = $customer->shop->name;
        $language     = DB::table('languages')->where('code', $conversation->language_code)->value('name') ?: 'English';
        $businessName = $this->businessName($customer) ?? 'not given';
        $country      = DB::table('countries')->where('code', data_get($customer->location, 0))->value('name') ?: 'not given';
        $shopType     = $customer->shop_type ? CustomerShopTypeEnum::labels()[$customer->shop_type->value] : 'not known yet';
        $families     = DB::table('customer_product_purchases')
            ->join('product_categories', 'product_categories.id', '=', 'customer_product_purchases.family_id')
            ->where('customer_product_purchases.customer_id', $customer->id)
            ->groupBy('product_categories.id', 'product_categories.name')
            ->orderByRaw('sum(customer_product_purchases.orders_12m) desc, sum(customer_product_purchases.spend_12m) desc')
            ->limit(8)
            ->pluck('product_categories.name')
            ->implode(', ') ?: 'none yet';

        return <<<PROMPT
You are the range advisor for trade customers of $shopName, a wholesaler. You help a retailer find products for their shop.

What we know about this customer:
Business name: $businessName
Country: $country
Shop type: $shopType
Product families they order most: $families

Reply in $language.

Rules:
- Only recommend products returned by the tools, by their ids, through present_products.
- Never state prices, discounts, stock quantities, delivery times or how many shops buy something.
- Never mention other customers.
- Do not invent product claims beyond the product and family names.
- Each product comes with a why line. When you say why you suggest something, say no more than its why line says: write "shops like yours" only when the why line says so, and "shops in your area" when that is what it says.
- What the customer orders is only what is listed above. Do not say what else they stock or sell.
- If the customer says what kind of shop they run and the shop type is not known yet, call save_shop_type once.
- Ask at most one short question when the request is too vague to search; otherwise search first.
- Politely decline anything that is not about finding products for their shop, and steer back to it.
- Keep the reply to two or three short sentences.
- In present_products, put products that fit the request or sell well for similar shops in must_haves, and complementary or previously ordered ones in add_ons.
- Do not paste tool results or product ids into the reply text.
PROMPT;
    }

    /**
     * Same rule as InferCustomerShopType::getBusinessName: the company name, unless every word of it
     * is in the contact name (a sole trader who typed their own name).
     */
    private function businessName(Customer $customer): ?string
    {
        $companyName  = trim((string) $customer->company_name);
        $words        = fn (string $text): array => array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($text))));
        $companyWords = $words($companyName);

        if (!$companyWords || !array_diff($companyWords, $words((string) $customer->contact_name))) {
            return null;
        }

        return $companyName;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customer = Customer::find($command->argument('customer'));
        if (!$customer) {
            $command->error('Customer not found');

            return 1;
        }

        if ($ulid = $command->option('conversation')) {
            $conversation = ShoppingAssistantConversation::where('ulid', $ulid)->where('customer_id', $customer->id)->first();
            if (!$conversation) {
                $command->error('No conversation '.$ulid.' for this customer');

                return 1;
            }
        } else {
            $conversation = StartShoppingAssistantConversation::run($customer, ShoppingAssistantEntryPointEnum::COMMAND);
        }

        $result  = $this->handle($conversation, (string) $command->argument('message'));
        $message = $result['message'];

        $command->info('Conversation '.$conversation->ulid);
        $command->line($result['reply']);

        foreach ($result['groups'] as $group => $products) {
            if ($products->isEmpty()) {
                continue;
            }
            $command->line($group);
            $command->table(['Code', 'Name', 'Reason'], $products->map(fn (Product $product) => [$product->code, $product->name, $product->assistant_reason])->all());
        }

        foreach ($result['follow_ups'] as $followUp) {
            $command->line('> '.$followUp);
        }

        if ($message->tool_calls) {
            $command->table(['Tool', 'Products', 'ms'], collect($message->tool_calls)->map(fn (array $toolCall) => [$toolCall['name'], count($toolCall['product_ids']), $toolCall['ms']])->all());
        }

        if ($message->flags) {
            $command->warn('Flags: '.implode(', ', $message->flags));
        }

        $command->line(sprintf(
            'model calls %d, prompt tokens %d, completion tokens %d, cost $%s, %d ms',
            $message->model_calls,
            $message->prompt_tokens,
            $message->completion_tokens,
            $message->cost,
            $message->latency_ms
        ));

        return 0;
    }
}
