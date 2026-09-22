<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-17h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\CloseChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Chat\MetaChatSession\CloseMetaChatSession;
use App\Actions\Chat\MetaChatSession\SendMetaChatMessage;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Saying the ticket is settled, to the customer who raised it, and ending the conversation there.
 *
 * Only for a ticket whose reporter agreed to it when raising it: the closing note is written for
 * the ticket, and this sends it on to somebody outside the company.
 */
class EndTicketConversation
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * @return array{sent: bool, closed: bool, reason: string|null}
     */
    public function handle(Ticket $ticket, string $note, ?User $actor = null): array
    {
        $session = $ticket->source;

        if (!$session instanceof ChatSession && !$session instanceof MetaChatSession) {
            return ['sent' => false, 'closed' => false, 'reason' => 'no_conversation'];
        }

        if ($session->status === ChatSessionStatusEnum::CLOSED) {
            return ['sent' => false, 'closed' => false, 'reason' => 'already_closed'];
        }

        $note = trim($note);

        if ($note === '') {
            return ['sent' => false, 'closed' => false, 'reason' => 'nothing_to_say'];
        }

        $sent   = $this->say($session, $this->signed($note), $actor);
        $closed = $this->close($session, $actor);

        return [
            'sent'   => $sent === true,
            'closed' => $closed,
            'reason' => is_string($sent) ? $sent : null,
        ];
    }

    /**
     * The customer is owed a name for whoever is writing, and the person who fixed it is not one
     * they have spoken to. They get the company's voice, signed off as a developer.
     */
    private function signed(string $note): string
    {
        return $note."\n\n- ".__('Developer');
    }

    /**
     * @return true|string  true when it went out, otherwise why it did not
     */
    private function say(ChatSession|MetaChatSession $session, string $text, ?User $actor): true|string
    {
        if ($session instanceof ChatSession) {
            // Sent as an agent without an id: no name is put to it in the thread, and the email
            // channel only forwards what an agent said.
            SendChatMessage::make()->handle($session, [
                'message_text' => $text,
                'message_type' => ChatMessageTypeEnum::TEXT->value,
                'sender_type'  => ChatSenderTypeEnum::AGENT->value,
                'sender_id'    => null,
            ]);

            return true;
        }

        // WhatsApp only carries free text within a day of the customer's last message. A ticket
        // settled later cannot be answered there, so the conversation is closed in silence
        // rather than pretending the customer was told.
        if (!$session->can_send_non_template_message) {
            return 'whatsapp_window_closed';
        }

        $agent = $this->whatsappSender($session, $actor);

        if (!$agent) {
            return 'no_agent';
        }

        try {
            $result = SendMetaChatMessage::make()->handle($session, $agent, ['message_text' => $text]);
        } catch (Throwable $exception) {
            return 'send_failed';
        }

        return ($result['ok'] ?? false) ? true : 'send_failed';
    }

    private function close(ChatSession|MetaChatSession $session, ?User $actor): bool
    {
        $agentId = $this->assignedAgent($session)?->id;

        try {
            if ($session instanceof ChatSession) {
                CloseChatSession::make()->handle($session, $agentId, ChatActorTypeEnum::SYSTEM);
            } else {
                CloseMetaChatSession::make()->handle($session, $agentId, ChatActorTypeEnum::SYSTEM);
            }
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }

    private function assignedAgent(ChatSession|MetaChatSession $session): ?ChatAgent
    {
        return $session->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->latest('id')
            ->first()?->chatAgent;
    }

    /**
     * WhatsApp records who sent it, and the customer sees the shop rather than a name, so the
     * agent who held the chat stands in, or the person settling the ticket if nobody did.
     */
    private function whatsappSender(MetaChatSession $session, ?User $actor): ?ChatAgent
    {
        $assigned = $this->assignedAgent($session);

        if ($assigned) {
            return $assigned;
        }

        return $actor ? $this->chatAgentProfileFor($actor) : null;
    }
}
