<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Turns the stored recipient selection into chunks of sendable rows.
 *
 * recipients_list holds phone numbers and nothing else, so the name, customer and chat
 * session behind each number are looked up again here. The selection stays the source of
 * truth: a number whose audience row has since disappeared is still sent to, with only
 * the phone number known about it.
 */
class PrepareWhatsappCampaignRecipients
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function tags(): array
    {
        return ['send_whatsapp_campaign'];
    }

    private const CHUNK_SIZE = 50;

    public function handle(WhatsappCampaign $campaign): void
    {
        $phoneKeys = array_values(array_filter(
            Arr::pluck($campaign->recipients_list ?? [], 'phone_number')
        ));

        if (!$phoneKeys) {
            return;
        }

        $resolved = $this->resolveRecipients($campaign, $phoneKeys);

        foreach (array_chunk($this->buildRows($phoneKeys, $resolved), self::CHUNK_SIZE) as $rows) {
            ProcessSendWhatsappCampaign::dispatch($campaign->id, $rows);
        }
    }

    /**
     * @param  array<int, string>  $phoneKeys
     * @return array<string, array<string, mixed>> keyed by recipient_key
     */
    private function resolveRecipients(WhatsappCampaign $campaign, array $phoneKeys): array
    {
        $query = GetWhatsappRecipientsQuery::run(
            $campaign->shop,
            $this->readChannels($campaign),
            Arr::get($campaign->recipients_recipe ?? [], 'customer_filters', [])
        );

        return $query->get()
            ->filter(fn ($row) => in_array($row->recipient_key, $phoneKeys, true))
            ->keyBy('recipient_key')
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Mirrors IndexWhatsappCampaignRecipients::readChannels(), which falls back to the
     * contacted channel. A campaign selected under that default stores no channels, so
     * reading the recipe literally here would resolve nobody and send every recipient as
     * a bare phone number.
     *
     * @return array<string, bool>
     */
    private function readChannels(WhatsappCampaign $campaign): array
    {
        $requested = Arr::get($campaign->recipients_recipe ?? [], 'channels');

        if (!is_array($requested) || empty(array_filter($requested, fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)))) {
            return ['contacted' => true, 'subscriber' => false, 'customers' => false];
        }

        $channels = [];

        foreach (GetWhatsappRecipientsQuery::CHANNELS as $channel) {
            $channels[$channel] = filter_var(Arr::get($requested, $channel, false), FILTER_VALIDATE_BOOLEAN);
        }

        return $channels;
    }

    /**
     * recipient_type follows the identity the audience gave back: a known customer is
     * recorded as one, anyone else as the chat session they will be messaged through.
     * The session id is filled in later by ProcessSendWhatsappCampaign, which is what
     * creates the session, so an unresolved number carries a 0 until then.
     *
     * @param  array<int, string>  $phoneKeys
     * @param  array<string, array<string, mixed>>  $resolved
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(array $phoneKeys, array $resolved): array
    {
        $rows = [];

        foreach ($phoneKeys as $phoneKey) {
            $row = $resolved[$phoneKey] ?? [];

            $rows[] = [
                'phone'                => $phoneKey,
                'name'                 => Arr::get($row, 'name'),
                'customer_id'          => Arr::get($row, 'customer_id'),
                'meta_chat_session_id' => Arr::get($row, 'meta_chat_session_id'),
            ];
        }

        return $rows;
    }

    public string $commandSignature = 'whatsapp-campaign:prepare-recipients {campaign}';

    public function asCommand(Command $command): int
    {
        $campaign = WhatsappCampaign::where('slug', $command->argument('campaign'))->first();

        if (!$campaign) {
            $command->error('Campaign not found');

            return 1;
        }

        $this->handle($campaign);

        return 0;
    }
}
