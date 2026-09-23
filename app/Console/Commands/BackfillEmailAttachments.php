<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Console\Commands;

use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Actions\Comms\Mailbox\ImportPendingGmailAttachments;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Throwable;

/**
 * Mail imported before attachments were kept reads as if the customer forgot to attach the list
 * they said was attached. The files are still in the mailbox, so they are fetched again by the id
 * the message was imported with, under the same rules a new mail follows: everything from a
 * customer, pictures only from a stranger, no signature logos.
 *
 * A message that already has a file is left alone, and so is one whose mail has since been deleted.
 */
class BackfillEmailAttachments extends Command
{
    protected $signature = 'chat:backfill-email-attachments
                           {--shop= : Only this shop id}
                           {--since= : Only messages received on or after this date}
                           {--until= : Only messages received before this date}
                           {--message= : Only this chat message id}
                           {--chunk=50 : Messages to fetch per batch}
                           {--dry-run : Report what would be imported without writing}';

    protected $description = 'Re-read imported email for the attachments that were never kept';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $this->option('dry-run');

        $query = ChatMessage::whereNotNull('metadata->gmail_message_id')
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])
            ->when($this->option('shop'), fn ($q, $shopId) => $q->whereHas('chatSession', fn ($s) => $s->where('shop_id', (int) $shopId)))
            ->when($this->option('since'), fn ($q, $since) => $q->where('created_at', '>=', $since))
            ->when($this->option('until'), fn ($q, $until) => $q->where('created_at', '<', $until))
            ->when($this->option('message'), fn ($q, $id) => $q->whereKey((int) $id))
            ->whereDoesntHave('media', fn ($m) => $m->whereIn('collection_name', ['chat_images', 'chat_attachments']));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to re-read.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Would re-read ' : 'Re-reading ').$total.' message(s).');

        $bar      = $this->output->createProgressBar($total);
        $clients  = [];
        $imported = $withFiles = $missing = $failed = 0;

        $query->with('chatSession.shop')->chunkById((int) $this->option('chunk'), function ($messages) use (
            $dryRun,
            $bar,
            &$clients,
            &$imported,
            &$withFiles,
            &$missing,
            &$failed
        ) {
            foreach ($messages as $message) {
                $bar->advance();

                $session = $message->chatSession;
                $shop    = $session?->shop;

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

                $gmailMessageId = Arr::get($message->metadata, 'gmail_message_id');

                try {
                    $raw   = $client->getMessage($gmailMessageId);
                    $files = ImportPendingGmailAttachments::make()->download(
                        $client,
                        $gmailMessageId,
                        $raw,
                        trusted: (bool) $session->web_user_id
                    );
                } catch (Throwable $e) {
                    // Deleted from the mailbox, or the mailbox disconnected. Leave it alone.
                    $failed++;

                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->warn('Message '.$message->id.' ('.$gmailMessageId.'): '.$e->getMessage());
                    }

                    continue;
                }

                if ($files === []) {
                    if ($this->output->isVerbose()) {
                        $this->newLine();
                        $this->line('Message '.$message->id.' ('.$gmailMessageId.'): nothing to import');
                        $this->line('  attachments in mail: '.count(GmailMessageParser::attachments(Arr::get($raw, 'payload', []))));
                        $this->line('  drive links in html: '.(implode(', ', GmailMessageParser::driveFileIds(GmailMessageParser::htmlBody($raw))) ?: 'none'));
                        $this->line('  last drive error: '.($client->lastDriveError ?? 'none'));
                    }

                    continue;
                }

                $withFiles++;
                $imported += count($files);

                if (!$dryRun) {
                    SendChatMessage::make()->processMessageAttachments($message, $files);
                }

                foreach ($files as $file) {
                    @unlink($file->getPathname());
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(($dryRun ? 'Would import: ' : 'Imported: ').$imported.' file(s) on '.$withFiles.' message(s)');
        $this->line('No mailbox: '.$missing);
        $this->line('Failed to read: '.$failed);

        return self::SUCCESS;
    }
}
