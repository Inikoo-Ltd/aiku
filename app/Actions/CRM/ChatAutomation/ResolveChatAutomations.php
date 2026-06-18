<?php

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\CRM\ChatSession\SendChatMessage;
use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveChatAutomations
{
    use AsAction;

    /**
     * Resolve and fire all enabled automations for a session that match the given trigger.
     *
     * @return array<int, int> ids of automations that fired
     */
    public function handle(ChatSession $chatSession, ChatAutomationTriggerEnum $trigger): array
    {
        if (! $chatSession->shop_id) {
            return [];
        }

        $automations = ChatAutomation::query()
            ->forShop($chatSession->shop_id)
            ->forTrigger($trigger)
            ->enabled()
            ->orderBy('priority')
            ->get();

        if ($automations->isEmpty()) {
            return [];
        }

        $chatSession->loadMissing(['webUser.customer', 'shop', 'assignments.chatAgent.user']);

        $alreadySent = Arr::get($chatSession->metadata ?? [], 'automations_sent', []);
        $fired       = [];

        foreach ($automations as $automation) {
            if ($automation->send_once && in_array($automation->id, $alreadySent, true)) {
                continue;
            }

            $message = $this->renderVariables($automation->message, $chatSession);

            SendChatMessage::run($chatSession, [
                'message_text' => $message,
                'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
                'sender_id'    => null,
            ]);

            $this->markSent($automation, $chatSession, $alreadySent);
            $fired[] = $automation->id;
        }

        return $fired;
    }

    private function renderVariables(string $message, ChatSession $chatSession): string
    {
        $contactName = $chatSession->webUser?->customer?->contact_name
            ?? $chatSession->webUser?->username
            ?? $chatSession->guest_identifier
            ?? __('there');

        $agentName = $chatSession->assignments
            ?->sortByDesc('assigned_at')
            ->first()?->chatAgent?->user?->contact_name ?? '';

        return strtr($message, [
            '{customer_name}'  => $contactName,
            '{shop_name}'      => $chatSession->shop?->name ?? '',
            '{agent_name}'     => $agentName,
            '{business_hours}' => $this->businessHours($chatSession),
        ]);
    }

    private function businessHours(ChatSession $chatSession): string
    {
        $website = $chatSession->shop?->website;
        if (! $website) {
            return '';
        }

        $config   = \App\Actions\HumanResources\WorkSchedule\GetChatConfig::run($website);
        $schedule = Arr::get($config, 'schedule');

        if (! $schedule || empty($schedule['start']) || empty($schedule['end'])) {
            return '';
        }

        $start = substr((string) $schedule['start'], 0, 5);
        $end   = substr((string) $schedule['end'], 0, 5);

        return "$start–$end";
    }

    private function markSent(ChatAutomation $automation, ChatSession $chatSession, array &$alreadySent): void
    {
        if ($automation->send_once) {
            $alreadySent[] = $automation->id;

            $metadata = $chatSession->metadata ?? [];
            data_set($metadata, 'automations_sent', array_values(array_unique($alreadySent)));
            $chatSession->update(['metadata' => $metadata]);
        }

        $stats = $automation->stats ?? [];
        data_set($stats, 'sent_count', (int) Arr::get($stats, 'sent_count', 0) + 1);
        data_set($stats, 'last_sent_at', now()->toISOString());
        $automation->updateQuietly(['stats' => $stats]);
    }
}
