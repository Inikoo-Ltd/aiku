<?php

namespace App\Actions\Maintenance\Helpers;

use App\Models\Helpers\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMediaMissingUlid
{
    use AsAction;

    public function handle(Media $media): void
    {
        $media->update([
            'ulid' => Str::ulid(),
        ]);

    }

    public string $commandSignature = 'repair:media_ulid';

    public function asCommand(Command $command): void
    {
        $count = Media::whereNull('ulid')->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Media::orderBy('id', 'desc')->whereNull('ulid')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
        $command->info("Repaired $count media records.");
    }

}
