<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\ExternalLink;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Models\Web\ExternalLink;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class RecheckExternalLinkStatuses
{
    use AsAction;

    private const int CONCURRENCY = 5;

    public string $jobQueue = 'long-low-priority';

    public function handle(): int
    {
        $checkedLinks = 0;

        ExternalLink::query()->orderBy('id')->chunkById(self::CONCURRENCY, function (Collection $externalLinks) use (&$checkedLinks) {
            $responses = Http::pool(fn (Pool $pool) => $externalLinks->map(
                fn (ExternalLink $externalLink) => $pool->as((string) $externalLink->id)
                    ->withHeaders(['User-Agent' => AuditWebsite::USER_AGENT])
                    ->connectTimeout(10)
                    ->timeout(20)
                    ->get($externalLink->url)
            )->all());

            foreach ($externalLinks as $externalLink) {
                $response = $responses[(string) $externalLink->id] ?? null;

                $externalLink->update(['status' => $response instanceof Response ? (string) $response->status() : 'error']);
                $checkedLinks++;
            }
        });

        return $checkedLinks;
    }

    public function getCommandSignature(): string
    {
        return 'external_links:check_status';
    }

    public function asCommand(Command $command): int
    {
        $command->line($this->handle().' external links checked');

        return 0;
    }
}
