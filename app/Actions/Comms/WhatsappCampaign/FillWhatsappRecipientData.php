<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Events\WhatsappCampaignFillProgressEvent;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\WhatsappCampaign;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public string $jobQueue = 'ses';

    private const CHUNK_SIZE = 1000;

    /**
     * One slice per call rather than the whole audience in one job. An audience in the tens of
     * thousands does not fit a worker timeout, and the supervisor's retries would restart the
     * walk from the first row every time it did not, so the job re-dispatches itself from where
     * it stopped and each attempt only ever replays its own slice.
     *
     * $afterId is where the previous slice ended. Rows already carrying a snapshot are skipped,
     * so a replayed slice costs a read rather than a re-resolve.
     *
     * $generation is which run of the fill this chain belongs to. Changing the audience or the
     * template bumps the campaign's generation, so a chain started before that change finds its
     * number stale and stops here rather than walking on with tags nobody selected any more.
     */
    public function handle(WhatsappCampaign $campaign, ?int $afterId = null, ?int $generation = null): void
    {
        $generation ??= $campaign->fillGeneration();

        if ($campaign->fresh()?->fillGeneration() !== $generation) {
            return;
        }

        $tags = $this->readTemplateTags($campaign);
        $shop = $campaign->shop;

        if (!$tags) {
            $this->fillTagless($campaign);
            $this->broadcastProgress($campaign);

            return;
        }

        $recipients = $campaign->recipients()
            ->whereNull('whatsapp_delivery_channel_id')
            ->whereNull('data')
            ->when($afterId, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(self::CHUNK_SIZE)
            ->get();

        if ($recipients->isEmpty()) {
            $this->broadcastProgress($campaign);

            return;
        }

        $customers = $this->customersFor($recipients);

        foreach ($recipients as $recipient) {
            $this->writeSnapshot($recipient->id, $this->resolveFor($recipient, $tags, $shop, $customers));
        }

        $this->broadcastProgress($campaign);

        self::dispatch($campaign, $recipients->last()->id, $generation);
    }

    /**
     * Written straight to the table rather than through the model, so updated_at is left alone.
     * StoreWhatsappCampaignRecipients marks the rows of a save with updated_at and sweeps
     * whatever the mark did not reach, so a fill touching that column would look like a save
     * and rescue rows the user had just deselected.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function writeSnapshot(int $recipientId, array $snapshot): void
    {
        DB::table('whatsapp_recipients')
            ->where('id', $recipientId)
            ->update(['data' => json_encode($snapshot)]);
    }

    /**
     * What is left to resolve, counted rather than tracked: the rows still holding a null data
     * column are exactly the ones no slice has reached. Costs a count per slice and cannot drift
     * from the rows themselves the way a stored tally would.
     */
    private function broadcastProgress(WhatsappCampaign $campaign): void
    {
        $total   = $campaign->recipients()->whereNull('whatsapp_delivery_channel_id')->count();
        $pending = $campaign->recipientsPendingFill();

        WhatsappCampaignFillProgressEvent::dispatch($campaign, [
            'done'       => $total - $pending,
            'total'      => $total,
            'state'      => $pending > 0 ? 'filling' : 'finished',
            'started_at' => Arr::get($campaign->data, 'fill_started_at'),
        ]);
    }

    /**
     * A tagless template resolves to the same snapshot for every recipient, so it is one
     * statement rather than one per row. At campaign sizes in the tens of thousands the
     * per-row path is the whole cost of this job, and none of it buys a different answer.
     */
    private function fillTagless(WhatsappCampaign $campaign): void
    {
        DB::table('whatsapp_recipients')
            ->where('whatsapp_campaign_id', $campaign->id)
            ->whereNull('whatsapp_delivery_channel_id')
            ->update([
                'data' => json_encode([
                    'template_parameters' => [],
                    'missing_tags'        => [],
                    'merge_tags'          => [],
                    'resolved_at'         => now()->toIso8601String(),
                ]),
            ]);
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
