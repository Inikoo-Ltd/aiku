<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Console\Commands;

use App\Models\Chat\ChatMessage;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\HTMLSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Re-reads mail already imported, so it shows the way it was sent.
 *
 * Two faults are mended at once. Bodies were extracted with strip_tags, which removes the tags but
 * keeps what is between them, so every stylesheet in the head survived as text and the message read
 * "*{box-sizing:border-box}body{margin:0". And the markup was discarded outright, so the pictures,
 * the headings and the layout were never kept at all.
 *
 * Nothing is invented: every message is fetched again from Gmail by the id it was imported with.
 * A message whose mail has since been deleted from the mailbox is left exactly as it is.
 */
class BackfillEmailBodies extends Command
{
    protected $signature = 'chat:backfill-email-bodies
                           {--shop= : Only this shop id}
                           {--chunk=50 : Messages to fetch per batch}
                           {--dry-run : Report what would change without writing}';

    protected $description = 'Re-read imported email so its text is clean and its layout is kept';

    public function handle(HTMLSanitizer $sanitizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shopId = $this->option('shop');

        $query = ChatMessage::whereNotNull('metadata->gmail_message_id')
            ->whereNull('html_body')
            ->when($shopId, fn ($q) => $q->whereHas('chatSession', fn ($s) => $s->where('shop_id', (int) $shopId)));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to re-read.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Would re-read ' : 'Re-reading ').$total.' message(s).');

        $bar = $this->output->createProgressBar($total);
        $clients = [];
        $repaired = $missing = $failed = 0;

        $query->with('chatSession.shop')->chunkById((int) $this->option('chunk'), function ($messages) use (
            $sanitizer,
            $dryRun,
            $bar,
            &$clients,
            &$repaired,
            &$missing,
            &$failed
        ) {
            foreach ($messages as $message) {
                $bar->advance();

                $shop = $message->chatSession?->shop;

                if (!$shop) {
                    $missing++;

                    continue;
                }

                // One client per shop: building it re-reads the shop's credentials every time.
                $client = $clients[$shop->id] ??= GmailClient::forShop($shop);

                if (!$client) {
                    $missing++;

                    continue;
                }

                try {
                    $raw  = $client->getMessage(Arr::get($message->metadata, 'gmail_message_id'));
                    $text = GmailMessageParser::body($raw);
                    $html = $sanitizer->cleanEmail(GmailMessageParser::htmlBody($raw));
                } catch (Throwable) {
                    // Deleted from the mailbox, or the mailbox disconnected. Leave it alone.
                    $failed++;

                    continue;
                }

                if ($text === '' && $html === '') {
                    $missing++;

                    continue;
                }

                $repaired++;

                if ($dryRun) {
                    continue;
                }

                $message->update(array_filter([
                    'message_text' => $text !== '' ? $text : null,
                    'html_body'    => $html !== '' ? $html : null,
                ]));
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($dryRun ? 'Would repair: ' : 'Repaired: ').$repaired);
        $this->line('No longer in the mailbox: '.$missing);
        $this->line('Failed to read: '.$failed);

        return self::SUCCESS;
    }
}
