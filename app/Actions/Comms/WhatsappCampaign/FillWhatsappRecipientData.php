<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves a campaign template's merge tags for every stored recipient and keeps the answers on
 * the row, so the send reads values rather than re-deriving them.
 *
 * Without this SendWhatsappDeliveryChannel resolves per recipient at send time, which costs a
 * customer load plus a fresh orders and invoices lookup for each tag that reads one. Doing it
 * here moves that off the send path and onto the picker, where the audience is already being
 * walked and nobody is waiting on Meta.
 *
 * Only unbatched rows are touched. A row already handed to a delivery channel is being sent
 * with the values it was given, and rewriting them underneath an in flight send would change
 * the message between the payload and the record kept of it.
 */
class FillWhatsappRecipientData
{
    use AsAction;
    use WithWhatsappCampaignAudience;

    public string $jobQueue = 'urgent';

    private const CHUNK_SIZE = 1000;

    public function handle(WhatsappCampaign $campaign): void
    {
        $tags = $this->readTemplateTags($campaign);
        $shop = $campaign->shop;

        $campaign->recipients()
            ->whereNull('whatsapp_delivery_channel_id')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $recipients) use ($tags, $shop) {
                $customers = $this->customersFor($recipients);

                foreach ($recipients as $recipient) {
                    $recipient->update(['data' => $this->resolveFor($recipient, $tags, $shop, $customers)]);
                }
            });
    }

    /**
     * A template with no merge tags still gets a snapshot written, empty. The send compares the
     * stored tag list against the template's to decide whether the snapshot still applies, and
     * a null data column is indistinguishable from one never filled, so it would fall back to
     * resolving on every send for the templates that need it least.
     *
     * @param  array<int, string>  $tags
     * @param  array<int, Customer>  $customers
     * @return array<string, mixed>
     */
    private function resolveFor(WhatsappRecipient $recipient, array $tags, ?Shop $shop, array $customers): array
    {
        $merged = $tags
            ? ResolveWhatsappTemplateTags::run($this->sessionFor($recipient, $shop, $customers), $tags)
            : ['values' => [], 'missing' => []];

        return [
            'template_parameters' => $merged['values'],
            'missing_tags'        => $merged['missing'],
            'merge_tags'          => $tags,
            'resolved_at'         => now()->toIso8601String(),
        ];
    }

    /**
     * The resolver is written against a MetaChatSession, but at this point most recipients have
     * none: ProcessSendWhatsappCampaign is what creates them, at send. Creating them here would
     * open a chat thread for every contact picked, including the ones a campaign never reaches.
     *
     * So the session is built in memory and never saved. The resolver reads only customer, shop,
     * phone_number and guest_identifier off it, and the picker already resolved all four onto the
     * recipient row, so this carries the same answers the saved session would have.
     *
     * Sharing the resolver rather than restating it is the point: FilterRecipientsByTemplateTags
     * already mirrors its semantics in SQL and says the two must be read together. A third copy
     * of these rules is one more place for them to drift.
     *
     * @param  array<int, Customer>  $customers
     */
    private function sessionFor(WhatsappRecipient $recipient, ?Shop $shop, array $customers): MetaChatSession
    {
        $session = new MetaChatSession([
            'shop_id'          => $shop?->id,
            'phone_number'     => '+'.$recipient->phone,
            'guest_identifier' => $recipient->recipient_name,
        ]);

        $session->setRelation('shop', $shop);
        $session->setRelation('customer', $recipient->recipient_type == 'Customer'
            ? ($customers[$recipient->recipient_id] ?? null)
            : null);

        return $session;
    }

    /**
     * One load for the chunk rather than one per row. The relations the resolver reaches through
     * are left to load themselves: only the tags actually in the template touch them, and eager
     * loading all of them would fetch orders and addresses for templates that name neither.
     *
     * @return array<int, Customer>
     */
    private function customersFor(Collection $recipients): array
    {
        $ids = $recipients->where('recipient_type', 'Customer')->pluck('recipient_id')->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return Customer::whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    public string $commandSignature = 'whatsapp-campaign:fill-recipient-data {campaign}';

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
