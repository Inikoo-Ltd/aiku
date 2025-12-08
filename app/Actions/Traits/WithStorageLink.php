<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 May 2023 07:49:27 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

trait WithStorageLink
{
    public function setStorageLink(string $path, string $link): array
    {

        $linkBase = public_path($path);
        $link = $linkBase.'/'.$link;
        $target = storage_path('app/public');

        if (! file_exists($linkBase)) {
            mkdir($linkBase, 0777, true);
        }
        if (! file_exists($target)) {
            mkdir($target, 0777, true);
        }

        if (file_exists($link)) {
            if (! is_link($link)) {
                return ['success' => false, 'target' => $target, 'link' => $link];
            }
            unlink($link);
        }
        symlink($target, $link);

        return ['success' => true, 'target' => $target, 'link' => $link];
    }
}
