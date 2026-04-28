<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 1 Apr 2026 14:33:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Comms\Mailshot\MailshotMergeContentsEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetMailshotMergeContents
{
    use AsAction;
    use WithAttributes;


    public function handle(): array
    {
        return MailshotMergeContentsEnum::contents();
    }

    public function asController(): array
    {
        return $this->handle();
    }
}
