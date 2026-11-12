<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantChallengeTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantEntryPointEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Reads a finished assistant conversation and records what stood in the customer's way: products we
 * do not sell, out of stock, price, carton size, shipping, the website, anything else (HELP-4608).
 *
 * The topic is a short English label so conversations in any language about the same thing land on
 * one row of the weekly Top 20; the topics already used for each type are passed in to be reused.
 * The quote is kept only when it is the customer's own words, in their language.
 *
 * Its cost is not a shopping_assistant_model_calls row, so it never counts against a website's
 * monthly budget; the AI gateway records it in ai_usages under this class.
 */
class TagShoppingAssistantConversationChallenges
{
    use AsAction;
    use WithAIGateway;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 120;
    public int $jobTries = 1;

    public string $commandSignature = 'shopping-assistant:tag-challenges {--l|limit=500 : Most conversations to queue} {--idle=120 : Minutes since the last message before a conversation is read}';

    public string $commandDescription = 'Queue AI shopping assistant conversations to be read for customer challenges';

    private const int MAX_MESSAGES = 30;
    private const int KNOWN_TOPICS = 40;

    public function handle(ShoppingAssistantConversation $conversation): void
    {
        if ($conversation->wiped_at) {
            return;
        }

        $startedAt = now();

        $messages = $conversation->messages()
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(self::MAX_MESSAGES)
            ->get(['id', 'role', 'body'])
            ->reverse()
            ->values();

        $customerText = $messages->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->pluck('body')->implode("\n");

        if (trim($customerText) === '') {
            $this->save($conversation, [], $startedAt);

            return;
        }

        $challenges = $this->askModel($conversation->group_id, $messages, $customerText);

        if ($challenges === false) {
            return;
        }

        $this->save($conversation, $challenges ?? [], $startedAt);
    }

    /**
     * @param  Collection<int, ShoppingAssistantMessage>  $messages
     *
     * False when the model could not be reached (tried again tomorrow); null when it answered with
     * nothing usable (not tried again, so a reply it cannot get right is not paid for every night).
     *
     * @return false|null|array<int, array{type: string, topic: string, quote: ?string}>
     */
    private function askModel(int $groupId, Collection $messages, string $customerText): false|null|array
    {
        $types = ShoppingAssistantChallengeTypeEnum::definitions();

        $transcript = $messages
            ->map(fn (ShoppingAssistantMessage $message) => ($message->role === ShoppingAssistantMessageRoleEnum::CUSTOMER ? 'Customer: ' : 'Assistant: ').Str::limit((string) $message->body, 1000))
            ->implode("\n\n");

        $prompt = "A trade customer (a shop owner buying wholesale) talked with our AI shopping assistant. List only the obstacles to buying that the CUSTOMER raised. "
            ."Ignore what the assistant said unless the customer reacted to it. A plain question is not an obstacle: record it only when the customer wanted something we could not give, "
            ."or said that something put them off or got in their way. Most conversations have none: then return an empty list.\n\n"
            ."Types:\n".collect($types)->map(fn (string $definition, string $type) => "- {$type}: {$definition}")->implode("\n")."\n\n"
            ."topic: 2 to 5 lowercase English words naming the thing, never the customer (\"shungite jewellery\", \"delivery to norway\", \"carton of 24 too big\"). "
            ."Reuse one of the known topics below when it means the same thing.\n"
            ."quote: the customer's own words that show it, copied exactly in their language, at most 200 characters.\n\n"
            ."Known topics:\n".json_encode($this->knownTopics($groupId), JSON_UNESCAPED_UNICODE)."\n\n"
            ."Conversation:\n".$transcript;

        try {
            $response = $this->aiRequest()
                ->connectTimeout(10)
                ->timeout(60)
                ->post('chat/completions', [
                    'model'       => $this->aiModel((string) config('services.shopping_assistant.challenges_model')),
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'tools'       => [[
                        'type'     => 'function',
                        'function' => [
                            'name'        => 'record_challenges',
                            'description' => 'Record the obstacles to buying the customer raised; an empty list when there are none.',
                            'parameters'  => [
                                'type'       => 'object',
                                'properties' => [
                                    'challenges' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'properties' => [
                                                'type'  => ['type' => 'string', 'enum' => array_keys($types)],
                                                'topic' => ['type' => 'string'],
                                                'quote' => ['type' => 'string'],
                                            ],
                                            'required'   => ['type', 'topic', 'quote'],
                                        ],
                                    ],
                                ],
                                'required'   => ['challenges'],
                            ],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'function', 'function' => ['name' => 'record_challenges']],
                    'max_tokens'  => 1000,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Shopping assistant challenge tagging failed: '.$exception->getMessage());

            return false;
        }

        if (!$response->successful()) {
            Log::warning('Shopping assistant challenge tagging failed: '.$response->status().' '.Str::limit($response->body(), 300));

            return false;
        }

        $arguments = Arr::get($response->json(), 'choices.0.message.tool_calls.0.function.arguments');
        $decoded   = is_string($arguments) ? json_decode($arguments, true) : $arguments;

        if (!is_array($decoded) || !is_array($decoded['challenges'] ?? null)) {
            return null;
        }

        return $this->clean($decoded['challenges'], $customerText);
    }

    /**
     * @param  array<int, mixed>  $challenges
     *
     * @return array<int, array{type: string, topic: string, quote: ?string}>
     */
    public function clean(array $challenges, string $customerText): array
    {
        $normalisedCustomerText = $this->normalise($customerText);

        return collect($challenges)
            ->filter(fn ($challenge) => is_array($challenge) && ShoppingAssistantChallengeTypeEnum::tryFrom((string) ($challenge['type'] ?? '')))
            ->map(function (array $challenge) use ($normalisedCustomerText) {
                $topic = Str::limit(Str::lower(trim(preg_replace('/\s+/u', ' ', (string) ($challenge['topic'] ?? '')))), 80, '');
                $quote = Str::limit(trim((string) ($challenge['quote'] ?? '')), 200, '');

                return [
                    'type'  => (string) $challenge['type'],
                    'topic' => $topic,
                    'quote' => $quote !== '' && str_contains($normalisedCustomerText, $this->normalise($quote)) ? $quote : null,
                ];
            })
            ->filter(fn (array $challenge) => $challenge['topic'] !== '')
            ->unique(fn (array $challenge) => $challenge['type'].'|'.$challenge['topic'])
            ->take(5)
            ->values()
            ->all();
    }

    private function normalise(string $text): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function knownTopics(int $groupId): array
    {
        return DB::table('shopping_assistant_challenges')
            ->join('shopping_assistant_conversations', 'shopping_assistant_conversations.id', '=', 'shopping_assistant_challenges.shopping_assistant_conversation_id')
            ->where('shopping_assistant_conversations.group_id', $groupId)
            ->where('shopping_assistant_challenges.created_at', '>=', now()->subDays(90))
            ->where('shopping_assistant_challenges.topic', '!=', '')
            ->selectRaw('shopping_assistant_challenges.type, shopping_assistant_challenges.topic, count(*) as uses')
            ->groupBy('shopping_assistant_challenges.type', 'shopping_assistant_challenges.topic')
            ->orderByDesc('uses')
            ->get()
            ->groupBy('type')
            ->map(fn ($rows) => $rows->take(self::KNOWN_TOPICS)->pluck('topic')->values()->all())
            ->all();
    }

    /**
     * @param  array<int, array{type: string, topic: string, quote: ?string}>  $challenges
     */
    private function save(ShoppingAssistantConversation $conversation, array $challenges, Carbon $startedAt): void
    {
        DB::transaction(function () use ($conversation, $challenges, $startedAt) {
            if (ShoppingAssistantConversation::whereKey($conversation->id)->lockForUpdate()->value('wiped_at')) {
                return;
            }

            DB::table('shopping_assistant_challenges')->where('shopping_assistant_conversation_id', $conversation->id)->delete();

            DB::table('shopping_assistant_challenges')->insert(array_map(fn (array $challenge) => [
                'shopping_assistant_conversation_id' => $conversation->id,
                'type'                               => $challenge['type'],
                'topic'                              => $challenge['topic'],
                'quote'                              => $challenge['quote'],
                'created_at'                         => $conversation->last_message_at ?? now(),
            ], $challenges));

            $conversation->update(['challenges_checked_at' => $startedAt]);
        });
    }

    /**
     * Conversations of customers (staff and command line tests left out) that went quiet at least
     * $idleMinutes ago and were never read, or have new messages since they were.
     */
    public function pendingQuery(int $idleMinutes): Builder
    {
        return ShoppingAssistantConversation::query()
            ->leftJoin('customers', 'customers.id', '=', 'shopping_assistant_conversations.customer_id')
            ->whereNull('shopping_assistant_conversations.wiped_at')
            ->whereNotIn('shopping_assistant_conversations.entry_point', [ShoppingAssistantEntryPointEnum::COMMAND->value, ShoppingAssistantEntryPointEnum::PANEL->value])
            ->whereRaw('coalesce(customers.is_staff, false) = false')
            ->whereNotNull('shopping_assistant_conversations.last_message_at')
            ->where('shopping_assistant_conversations.last_message_at', '<=', now()->subMinutes($idleMinutes))
            ->where(fn ($query) => $query
                ->whereNull('shopping_assistant_conversations.challenges_checked_at')
                ->orWhereColumn('shopping_assistant_conversations.challenges_checked_at', '<', 'shopping_assistant_conversations.last_message_at'))
            ->select('shopping_assistant_conversations.*');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $conversations = $this->pendingQuery((int) $command->option('idle'))
            ->orderByDesc('shopping_assistant_conversations.last_message_at')
            ->limit((int) $command->option('limit'))
            ->get();

        foreach ($conversations as $conversation) {
            self::dispatch($conversation);
        }

        $command->info($conversations->count().' conversations queued');

        return 0;
    }
}
