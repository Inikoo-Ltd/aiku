<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Discord DM through the aiku bot, shared by the MCP discord-message-tool and the alerts that
 * page an engineer directly.
 */
class SendDiscordDirectMessage
{
    use AsAction;

    private const int DISCORD_MESSAGE_LIMIT = 2000;

    /**
     * @return string link to the DM
     *
     * @throws RuntimeException when the bot is not configured, the user has no Discord id or Discord refuses
     */
    public function handle(User $recipient, string $text): string
    {
        $botToken = config('services.discord.bot_token');
        if (!$botToken) {
            throw new RuntimeException('Discord bot token is not configured.');
        }

        $discordUserId = data_get($recipient->settings, 'discord_user_id');
        if (!$discordUserId) {
            throw new RuntimeException($recipient->username.' has no Discord user id in their settings.');
        }

        $discord = Http::withToken($botToken, 'Bot')->baseUrl('https://discord.com/api/v10')->acceptJson();

        $channel = $discord->post('/users/@me/channels', ['recipient_id' => (string) $discordUserId]);
        if (!$channel->successful()) {
            throw new RuntimeException('Discord refused to open a DM with '.$recipient->username.': '.$channel->json('message', $channel->status()));
        }
        $channelId = $channel->json('id');

        foreach ($this->chunks($text) as $chunk) {
            $sent = $discord->post('/channels/'.$channelId.'/messages', ['content' => $chunk]);
            if (!$sent->successful()) {
                throw new RuntimeException('Discord did not accept the message for '.$recipient->username.': '.$sent->json('message', $sent->status()).'. They may have DMs from server members turned off.');
            }
        }

        return 'https://discord.com/channels/@me/'.$channelId;
    }

    /**
     * Splits on the last newline before Discord's limit so code blocks and lists are not cut mid-line.
     *
     * @return array<int, string>
     */
    private function chunks(string $text): array
    {
        $chunks = [];
        while (mb_strlen($text) > self::DISCORD_MESSAGE_LIMIT) {
            $head = mb_substr($text, 0, self::DISCORD_MESSAGE_LIMIT);
            $cut  = mb_strrpos($head, "\n") ?: self::DISCORD_MESSAGE_LIMIT;

            $chunks[] = mb_substr($text, 0, $cut);
            $text     = ltrim(mb_substr($text, $cut), "\n");
        }
        $chunks[] = $text;

        return $chunks;
    }
}
