<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 21:42:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class UpdateWebpageIsDifferentWhenLoggedIn
{
    use AsAction;

    public function handle(Webpage $webpage): Webpage
    {
        $webBlocks = Arr::get($webpage->published_layout, 'web_blocks', []);

        $isDifferent = false;
        /** @var WebBlock $webBlock */
        foreach ($webBlocks as $webBlock) {
            if (!Arr::get($webBlock, 'show')) {
                continue;
            }
            if (Arr::get($webBlock, 'visibility.in') != Arr::get($webBlock, 'visibility.out')) {
                $isDifferent = true;
            }
        }
        $webpage->update(
            [
                'is_different_when_logged_in' => $isDifferent
            ]
        );

        return $webpage;
    }

    public string $commandSignature = 'webpage:update-is-different-when-logged-in {webpage?}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('webpage')) {
            $webpage = Webpage::where('slug', $command->argument('webpage'))->firstOrFail();
            $this->handle($webpage);
            $command->info("Updated webpage is_different_when_logged_in: $webpage->slug ".$webpage->is_different_when_logged_in ? 'Yes' : 'No');

            return;
        }

        $totalWebpages = Webpage::count();
        $progressBar   = new ProgressBar($command->getOutput(), $totalWebpages);
        $progressBar->setFormat('debug');
        $progressBar->start();

        Webpage::chunk(1000, function ($webpages) use ($progressBar,$command) {
            foreach ($webpages as $webpage) {
                $this->handle($webpage);
                if($webpage->is_different_when_logged_in){
                    $command->info(" webpage: $webpage->slug  is different when logged in");
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
    }


}
