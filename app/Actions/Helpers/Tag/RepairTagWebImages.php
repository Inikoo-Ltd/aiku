<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Nov 2025 14:34:35 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Tag;

use App\Models\Helpers\Tag;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairTagWebImages
{
    use AsAction;

    public string $commandSignature = 'tag:repair-web-images {--chunk=1000}';

    /**
     * Update web_image JSON for a given tag if it has an image.
     */
    public function handle(Tag $tag): void
    {
        if ($tag->image) {
            // Keep the same size used elsewhere for tag thumbnails
            $tag->update([
                'web_image' => $tag->imageSources(30, 30)
            ]);
        }
    }

    public function asCommand(Command $command): int
    {
        $chunkSizeOption = (int)($command->option('chunk') ?? 1000);
        $chunkSize       = $chunkSizeOption > 0 ? $chunkSizeOption : 1000;

        $total = (int) Tag::count();
        if ($total === 0) {
            $command->warn('No tags found.');
            return 0;
        }

        $command->line("Repairing web images for {$total} tags in chunks of {$chunkSize}...");

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('[%bar%] %percent:3s%% | %current%/%max% | Elapsed: %elapsed:6s% | Remaining: %remaining:6s% | ETA: %estimated:-6s%');
        $bar->setRedrawFrequency(max(1, (int) floor($total / 200)));
        $bar->start();

        $processed = 0;
        $updated   = 0;

        Tag::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($tags) use (&$processed, &$updated, $bar) {
                foreach ($tags as $tag) {
                    // Only update when an image relation exists
                    if ($tag->image) {
                        $tag->update([
                            'web_image' => $tag->imageSources(30, 30)
                        ]);
                        $updated++;
                    }
                    $processed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine(2);
        $command->info("Processed {$processed} tags. Updated web_image for {$updated} tags.");

        return 0;
    }
}
