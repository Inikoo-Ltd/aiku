<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Clocking;

use App\Actions\HumanResources\Clocking\Traits\SetClockingPhotoFromImage;
use App\Models\HumanResources\Clocking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Storing the kiosk snapshot costs more than the clocking itself - the media library conversions
 * were roughly two thirds of the camera kiosk's response, with a queue of people waiting behind
 * it - and the photo is evidence after the fact, never something the employee waits to be told.
 *
 * So it is attached after the clocking has already been confirmed on screen. A snapshot lost to a
 * failed job costs nothing; a clocking lost to one costs someone their hours, which is why the
 * image never travels with the clocking's own transaction.
 */
class AttachClockingPhoto implements ShouldQueue
{
    use AsAction;

    public int $tries = 1;

    public function handle(Clocking $clocking, string $encodedImage, string $originalFilename, string $extension): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'clocking-photo-');

        if ($imagePath === false) {
            return;
        }

        try {
            file_put_contents($imagePath, base64_decode($encodedImage));

            SetClockingPhotoFromImage::run(
                clocking: $clocking,
                imagePath: $imagePath,
                originalFilename: $originalFilename,
                extension: $extension
            );
        } finally {
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    }
}
