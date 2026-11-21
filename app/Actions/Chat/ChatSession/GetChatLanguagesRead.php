<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The languages a member of staff reads, so auto translation leaves them as written. Few set it
 * themselves, so until they do it is taken from what they wrote: a language they answered
 * customers in by their own hand, Aura's drafts and automatic messages aside, at least ten times
 * in the last three months.
 */
class GetChatLanguagesRead
{
    use AsAction;

    public const int MIN_MESSAGES = 10;

    /**
     * @return array<int, string>
     */
    public function handle(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $chosen = data_get($user->settings, 'chat_languages');

        if (is_array($chosen)) {
            return $chosen;
        }

        return Cache::remember("chat-languages-read:$user->id", now()->addDay(), fn () => $this->writtenIn($user));
    }

    /**
     * @return array<int, string>
     */
    private function writtenIn(User $user): array
    {
        return DB::table('chat_messages')
            ->join('chat_agents', 'chat_agents.id', '=', 'chat_messages.sender_id')
            ->join('languages', 'languages.id', '=', 'chat_messages.original_language_id')
            ->where('chat_agents.user_id', $user->id)
            ->where('chat_messages.sender_type', 'agent')
            ->where('chat_messages.created_at', '>', now()->subDays(90))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('chat_ai_drafts')->whereColumn('chat_ai_drafts.reply_message_id', 'chat_messages.id'))
            ->whereRaw("coalesce(chat_messages.metadata->>'is_automated', '') = ''")
            ->groupBy('languages.code')
            ->havingRaw('count(*) >= ?', [self::MIN_MESSAGES])
            ->orderByRaw('count(*) desc')
            ->pluck('languages.code')
            ->all();
    }
}
