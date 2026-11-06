<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\SendDiscordDirectMessage;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Description('Send a Discord direct message to a colleague, signed by the authenticated user. One way push: use it to hand over work, e.g. a summary of what was done plus the URL to test. Recipient is an aiku username; Discord markdown (code blocks, bullets, links) is preserved. Returns the DM link so the conversation can continue in Discord.')]
class DiscordMessageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'to'      => ['required', 'string'],
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $sender    = $request->user();
        $recipient = User::where('group_id', $sender->group_id)
            ->where('username', $request->string('to')->lower()->toString())
            ->first();

        if (!$recipient) {
            return Response::error('No aiku user with username "'.$request->string('to').'".');
        }

        try {
            $dmUrl = SendDiscordDirectMessage::run($recipient, '**'.$sender->contact_name.'** via aiku'."\n".$request->string('message')->toString());
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::json([
            'sent_to' => $recipient->username,
            'dm_url'  => $dmUrl,
        ]);
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
