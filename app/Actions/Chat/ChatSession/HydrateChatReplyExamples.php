<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\EmbedTexts;
use App\Models\Chat\ChatReplyExample;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Collects every customer message our agents answered, by archived email (the customer's mail
 * and our next reply in the thread) and by chat (the customer's last message before an agent
 * message), then embeds the customer messages not embedded yet. Safe to run again: pairs
 * already collected are skipped, so mail archived later is picked up on the next run.
 */
class HydrateChatReplyExamples
{
    use AsAction;

    public string $commandSignature = 'chat:hydrate-reply-examples {--l|limit= : Embed at most this many}';

    private const string QUOTE_START = '(\n\s*(On|El|Am|Le|Il|Op|W dniu|Dňa|Dne|Em|Den|På)\s[^\n]{0,200}(wrote|escribió|schrieb|a écrit|ha scritto|schreef|napisał|napísal|napsal|escreveu|skrev|írta)[^\n]*:|\n\s*-{2,}\s*(Original|Mensaje original|Ursprüngliche)|\n\s*>)';

    /**
     * @return array{collected: int, embedded: int}
     */
    public function handle(?int $limit = null): array
    {
        $collected = max(0, $this->collectEmails() + $this->collectChats()
            - ChatReplyExample::whereRaw('length(btrim(customer_wrote)) < 2 or length(btrim(reply)) < 2')->delete());

        return ['collected' => $collected, 'embedded' => $this->embed($limit)];
    }

    private function collectEmails(): int
    {
        return DB::affectingStatement(<<<'SQL'
            insert into chat_reply_examples (group_id, organisation_id, shop_id, source, source_id, customer_wrote, reply, replied_at, created_at, updated_at)
            select o.group_id, o.organisation_id, o.shop_id, 'email', o.id, regexp_replace(left(i.text, 4000), ?, '', 's'), regexp_replace(left(o.text, 4000), ?, '', 's'), o.sent_at, now(), now()
            from email_archive_messages o
            join lateral (
                select text, sent_at from email_archive_messages i
                where i.gmail_thread_id = o.gmail_thread_id and i.shop_id = o.shop_id and not i.is_outbound and i.sent_at < o.sent_at
                order by i.sent_at desc limit 1
            ) i on true
            where o.is_outbound and o.shop_id is not null and length(o.text) between 2 and 3000 and length(i.text) >= 2
              and not exists (
                  select 1 from email_archive_messages p
                  where p.gmail_thread_id = o.gmail_thread_id and p.is_outbound and p.sent_at > i.sent_at and p.sent_at < o.sent_at
              )
            on conflict (source, source_id) do nothing
            SQL, [self::QUOTE_START.'.*$', self::QUOTE_START.'.*$']);
    }

    private function collectChats(): int
    {
        return DB::affectingStatement(<<<'SQL'
            insert into chat_reply_examples (group_id, organisation_id, shop_id, source, source_id, customer_wrote, reply, replied_at, created_at, updated_at)
            select sh.group_id, sh.organisation_id, s.shop_id, 'chat', a.id, regexp_replace(left(c.message_text, 4000), ?, '', 's'), regexp_replace(left(a.message_text, 4000), ?, '', 's'), a.created_at, now(), now()
            from chat_messages a
            join chat_sessions s on s.id = a.chat_session_id
            join shops sh on sh.id = s.shop_id
            join lateral (
                select message_text, id from chat_messages c
                where c.chat_session_id = a.chat_session_id and c.sender_type in ('guest', 'user') and c.id < a.id and c.message_text is not null
                order by c.id desc limit 1
            ) c on true
            where a.sender_type = 'agent' and length(a.message_text) between 2 and 3000
              and not exists (select 1 from chat_messages p where p.chat_session_id = a.chat_session_id and p.sender_type = 'agent' and p.id > c.id and p.id < a.id)
            on conflict (source, source_id) do nothing
            SQL, [self::QUOTE_START.'.*$', self::QUOTE_START.'.*$']);
    }

    private function embed(?int $limit): int
    {
        $embedded = 0;

        ChatReplyExample::whereNull('embedding')->select(['id', 'customer_wrote'])->chunkById(100, function ($examples) use (&$embedded, $limit) {
            $vectors = EmbedTexts::run($examples->map(fn (ChatReplyExample $example) => mb_substr($example->customer_wrote, 0, 2000))->all(), true);

            if (!$vectors) {
                return false;
            }

            foreach ($examples->values() as $i => $example) {
                if (isset($vectors[$i])) {
                    $example->update(['embedding' => $vectors[$i]]);
                    $embedded++;
                }
            }

            return !$limit || $embedded < $limit;
        });

        return $embedded;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $result = $this->handle($command->option('limit') ? (int) $command->option('limit') : null);
        $command->info("Collected {$result['collected']} new pairs, embedded {$result['embedded']}.");

        return 0;
    }
}
