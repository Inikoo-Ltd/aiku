<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Models\Comms\WhatsappCampaign;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Turns the stored recipient selection into chunks of sendable rows.
 *
 * recipients_list holds phone numbers and nothing else, so the name and customer behind each
 * number are looked up again here, straight from the customers and chat sessions that carry
 * them. The selection stays the source of truth: the audience recipe is not re-applied, and a
 * number nothing is known about is still sent to with only its phone number.
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
        /* Re-checked here rather than trusted from the selection: a list saved before the
           audience started filtering unsendable numbers still holds them. */
        $phoneKeys = array_values(array_filter(
            Arr::pluck($campaign->recipients_list ?? [], 'phone_number'),
            fn ($phone) => GetWhatsappRecipientsQuery::isSendablePhone((string) $phone)
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
     * Looks up what is known about each selected number, and nothing else. The recipe is
     * deliberately not consulted: recipients_list is already the audience the user confirmed,
     * so re-applying the channels and filters here would drop contacts they picked whenever
     * the recipe was tightened, or a customer stopped matching it, after the selection.
     *
     * @param  array<int, string>  $phoneKeys
     * @return array<string, array<string, mixed>> keyed by recipient_key
     */
    private function resolveRecipients(WhatsappCampaign $campaign, array $phoneKeys): array
    {
        $sessions = $this->keyedByPhone(
            DB::table('meta_chat_sessions')
                ->where('shop_id', $campaign->shop_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->select(['customer_id', 'guest_identifier as name']),
            'phone_number',
            $phoneKeys
        );

        /* Layered over the sessions rather than merged with them: a real customer carries a
           better name than a chat session's WhatsApp profile label, and is what makes the
           recipient record a Customer rather than a bare session. */
        $customers = $this->keyedByPhone(
            DB::table('customers')
                ->where('shop_id', $campaign->shop_id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->select([DB::raw('id as customer_id'), DB::raw('contact_name as name')]),
            'phone',
            $phoneKeys
        );

        return array_replace($sessions, $customers);
    }

    /**
     * Matched on the digits only form of the number, the SQL twin of
     * GetWhatsappRecipientsQuery::normalisePhoneKey(), because that is the shape
     * recipients_list stores and the two have to agree for a row to be found at all.
     *
     * @param  array<int, string>  $phoneKeys
     * @return array<string, array<string, mixed>>
     */
    private function keyedByPhone(Builder $query, string $phoneColumn, array $phoneKeys): array
    {
        $key = sprintf("regexp_replace(%s, '[^0-9]', '', 'g')", $phoneColumn);

        return $query
            ->addSelect(DB::raw($key.' as recipient_key'))
            ->whereIn(DB::raw($key), $phoneKeys)
            ->get()
            ->keyBy('recipient_key')
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * A number nothing is known about is still sent to, carrying only its phone: the
     * selection is the source of truth, and ProcessSendWhatsappCampaign falls back to the
     * chat session it creates for the name and customer it needs to record the recipient.
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
                'phone'       => $phoneKey,
                'name'        => Arr::get($row, 'name'),
                'customer_id' => Arr::get($row, 'customer_id'),
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
