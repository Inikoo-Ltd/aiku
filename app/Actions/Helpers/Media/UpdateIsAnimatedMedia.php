<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:03 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Media;

use App\Models\Helpers\Media;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateIsAnimatedMedia
{
    use AsAction;

    public function handle(Media $media, ?string $imagePath = null): Media
    {

        $animated = false;
        if ($media->mime_type == 'image/gif') {

            if ($imagePath) {
                $fileHandler = fopen($imagePath, 'r');
            } else {
                $content     = Storage::disk(config('media-library.disk_name'))->get($media->getPath());
                $fileHandler = tmpfile();
                fwrite($fileHandler, $content);
                fseek($fileHandler, 0);
            }

            $animated = $this->isGifAnimated($fileHandler);



        }

        $media->update(['is_animated' => $animated]);
        return $media;

    }

    public function isGifAnimated($fh): bool
    {


        $count = 0;
        //an animated gif contains multiple "frames", with each frame having a
        //header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk);
        }

        fclose($fh);
        return $count > 1;
    }

    public function getCommandSignature(): string
    {
        return 'media:is_animated {media?}';
    }


    public function asCommand(Command $command): int
    {
        $media = $command->argument('media');
        if ($command->argument('media')) {
            try {
                $media = Media::where('slug', $media)->firstOrFail();
            } catch (Exception) {
                $command->error('Studio not found');
                return 1;
            }

            $this->processMedia($media, $command);


        } else {
            foreach (Media::where('mime_type', 'image/gif')->get() as $media) {
                $this->processMedia($media, $command);
            }
        }

        return 0;
    }


    private function processMedia(Media $media, Command $command): void
    {
        $isAnimated = $media->is_animated;
        $this->handle($media);

        $label = $media->is_animated ? "true" : "false";

        if ($isAnimated !== $media->is_animated) {

            $command->line("Studio $media->slug is_animated updated from $isAnimated to $label");
        } else {

            $command->line("Studio $media->slug is_animated is: $label");
        }
    }



}
