<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Send a Discord direct message to a colleague, signed by the authenticated user. One way push: use it to hand over work, e.g. a summary of what was done plus the URL to test. Recipient is an aiku username; Discord markdown (code blocks, bullets, links) is preserved. Returns the DM link so the conversation can continue in Discord.')]
class DiscordMessageTool extends Tool
{
    private const int DISCORD_MESSAGE_LIMIT = 2000;

    public function handle(Request $request): Response
    {
        $request->validate([
            'to'      => ['required', 'string'],
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $botToken = config('services.discord.bot_token');
        if (!$botToken) {
            return Response::error('Discord bot token is not configured.');
        }

        $sender    = $request->user();
        $recipient = User::where('group_id', $sender->group_id)
            ->where('username', $request->string('to')->lower()->toString())
            ->first();

        if (!$recipient) {
            return Response::error('No aiku user with username "'.$request->string('to').'".');
        }

        $discordUserId = data_get($recipient->settings, 'discord_user_id');
        if (!$discordUserId) {
            return Response::error($recipient->username.' has no Discord user id in their settings.');
        }

        $discord = Http::withToken($botToken, 'Bot')->baseUrl('https://discord.com/api/v10')->acceptJson();

        $channel = $discord->post('/users/@me/channels', ['recipient_id' => (string) $discordUserId]);
        if (!$channel->successful()) {
            return Response::error('Discord refused to open a DM with '.$recipient->username.': '.$channel->json('message', $channel->status()));
        }
        $channelId = $channel->json('id');

        $text = '**'.$sender->contact_name.'** via aiku'."\n".$request->string('message')->toString();

        foreach ($this->chunks($text) as $chunk) {
            $sent = $discord->post('/channels/'.$channelId.'/messages', ['content' => $chunk]);
            if (!$sent->successful()) {
                return Response::error('Discord did not accept the message for '.$recipient->username.': '.$sent->json('message', $sent->status()).'. They may have DMs from server members turned off.');
            }
        }

        return Response::json([
            'sent_to' => $recipient->username,
            'dm_url'  => 'https://discord.com/channels/@me/'.$channelId,
        ]);
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

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'to'      => $schema->string()->description('aiku username of the colleague to message')->required(),
            'message' => $schema->string()->description('Message body, Discord markdown allowed; long messages are split at 2000 characters')->required(),
        ];
    }
}
