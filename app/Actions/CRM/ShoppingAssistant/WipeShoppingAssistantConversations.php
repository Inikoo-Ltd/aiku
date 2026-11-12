<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantEvent;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantMessage;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Wipes what customers wrote and were told by the AI shopping assistant: message texts, tool
 * arguments, follow-ups, reason lines, hand-over questions, challenge quotes and topics (a topic is
 * AI text drawn from the customer's words). Counters, tokens, cost, product ids and challenge types
 * stay, so the report keeps its figures. A conversation that got text after its wipe (an answer or
 * a purchasing reply already on its way) is wiped again by the nightly run.
 *
 * Anonymising a customer wipes theirs at once; the nightly command wipes every conversation that
 * went quiet more than the group's retention period ago (Sysadmin > Settings > AI shopping
 * assistant, else services.shopping_assistant.retention_months), and refused questions as old.
 */
class WipeShoppingAssistantConversations
{
    use AsAction;

    public string $commandSignature = 'shopping-assistant:wipe-old {--dry-run : Only count what would be wiped}';

    public string $commandDescription = 'Wipe the texts of AI shopping assistant conversations older than the retention period';

    /**
     * @param  array<int, int>  $conversationIds
     */
    public function handle(array $conversationIds): void
    {
        foreach (array_chunk($conversationIds, 500) as $chunk) {
            DB::transaction(function () use ($chunk) {
                ShoppingAssistantMessage::whereIn('shopping_assistant_conversation_id', $chunk)
                    ->update(['body' => null, 'tool_calls' => null, 'follow_ups' => null]);
                ShoppingAssistantEvent::whereIn('shopping_assistant_conversation_id', $chunk)
                    ->update(['reason' => null]);
                DB::table('shopping_assistant_hand_overs')->whereIn('shopping_assistant_conversation_id', $chunk)->update(['question' => null]);
                DB::table('shopping_assistant_challenges')->whereIn('shopping_assistant_conversation_id', $chunk)->update(['quote' => null, 'topic' => '']);
                ShoppingAssistantConversation::whereIn('id', $chunk)->whereNull('wiped_at')->update(['wiped_at' => now()]);
            });
        }
    }

    public static function retentionMonths(Group $group): int
    {
        $months = (int) Arr::get($group->settings, 'shopping_assistant.retention_months');

        return $months > 0 ? $months : (int) config('services.shopping_assistant.retention_months');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry-run');

        foreach (Group::all() as $group) {
            $before = now()->subMonthsNoOverflow(self::retentionMonths($group));

            $conversations = ShoppingAssistantConversation::where('group_id', $group->id)
                ->whereRaw('coalesce(last_message_at, created_at) < ?', [$before])
                ->where(fn ($query) => $query
                    ->whereNull('wiped_at')
                    ->orWhereExists(fn ($messages) => $messages->selectRaw('1')->from('shopping_assistant_messages')
                        ->whereColumn('shopping_assistant_messages.shopping_assistant_conversation_id', 'shopping_assistant_conversations.id')
                        ->where(fn ($text) => $text->whereNotNull('body')->orWhereNotNull('tool_calls')->orWhereNotNull('follow_ups')))
                    ->orWhereExists(fn ($challenges) => $challenges->selectRaw('1')->from('shopping_assistant_challenges')
                        ->whereColumn('shopping_assistant_challenges.shopping_assistant_conversation_id', 'shopping_assistant_conversations.id')
                        ->where(fn ($text) => $text->whereNotNull('quote')->orWhere('topic', '!=', ''))));

            $refusals = DB::table('shopping_assistant_refusals')
                ->where('group_id', $group->id)
                ->whereNotNull('question')
                ->where('created_at', '<', $before);

            if ($dryRun) {
                $command->line("$group->slug: {$conversations->count()} conversations and {$refusals->count()} refused questions before {$before->toDateString()}");

                continue;
            }

            $wiped = 0;
            $conversations->select('id')->chunkById(500, function ($chunk) use (&$wiped) {
                $this->handle($chunk->pluck('id')->all());
                $wiped += $chunk->count();
            });
            $refused = $refusals->update(['question' => null]);

            $command->info("$group->slug: $wiped conversations and $refused refused questions wiped (before {$before->toDateString()})");
        }

        return 0;
    }
}
