<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Wechat;

use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Models\SysAdmin\Organisation;
use App\Services\Wecom\WecomArchiveClient;
use App\Services\Wecom\WecomArchiveException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class PullProcurementWechatArchive
{
    use AsAction;

    public string $commandSignature = 'procurement-wechat:pull {organisation? : organisation slug}';

    /**
     * WeCom numbers every archived message and keeps them only for a few days, so the position
     * is saved after each batch: a crash re-reads at most one batch, which the message id makes
     * harmless, and never skips one. A key that cannot open a message stops the pull without
     * moving on, so nothing is lost while the settings are being fixed.
     */
    public function handle(Organisation $organisation): int
    {
        $client = WecomArchiveClient::forProcurement($organisation);

        if (! $client) {
            return 0;
        }

        $lock = Cache::lock("procurement-wechat-pull:$organisation->id", 600);

        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->pull($organisation, $client);
        } finally {
            $lock->release();
        }
    }

    private function pull(Organisation $organisation, WecomArchiveClient $client): int
    {
        $seq       = (int) Arr::get($organisation->settings, 'procurement.wechat.last_seq', 0);
        $batchSize = (int) config('wecom.archive.batch_size');
        $stored    = 0;

        do {
            $batch = $client->chatData($seq, $batchSize);

            foreach ($batch as $chatData) {
                $message = $client->decrypt($chatData);

                if (StoreProcurementWechatMessage::run($organisation, $message)) {
                    $stored++;
                }

                $seq = max($seq, (int) $chatData['seq']);
            }

            $this->saveSeq($organisation, $seq);
        } while (count($batch) === $batchSize);

        return $stored;
    }

    private function saveSeq(Organisation $organisation, int $seq): void
    {
        $organisation->refresh();

        $settings = $organisation->settings ?? [];
        data_set($settings, 'procurement.wechat.last_seq', $seq);
        data_set($settings, 'procurement.wechat.last_pulled_at', now()->toIso8601String());
        $organisation->update(['settings' => $settings]);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::whereNotNull('settings->procurement->wechat->archive_secret')
            ->when($command->argument('organisation'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Organisation $organisation) => UpdateProcurementSettings::usesWechat($organisation));

        $total = 0;
        foreach ($organisations as $organisation) {
            try {
                $total += $this->handle($organisation);
            } catch (WecomArchiveException $exception) {
                Log::warning("WeCom chat archive pull failed for $organisation->slug: {$exception->getMessage()}");
                $command->error("$organisation->slug: {$exception->getMessage()}");
            }
        }

        $command->info("Stored $total WeChat message(s) across {$organisations->count()} organisation(s)");

        return 0;
    }
}
