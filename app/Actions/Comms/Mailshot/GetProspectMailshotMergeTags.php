<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 7 May 2026 10:45:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\ProspectMailshotMergeTagsEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetProspectMailshotMergeTags
{
    use AsAction;
    use WithAttributes;


    public function handle(): array
    {
        return ProspectMailshotMergeTagsEnum::tags();
    }

    public function asController(): array
    {
        return $this->handle();
    }
}
