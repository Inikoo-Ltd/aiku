<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 10 June 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\UI;

use App\Actions\Comms\Mailshot\GetMailshotMergeTags;
use App\Actions\OrgAction;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxMergeTagsEnum;
use App\Models\Comms\Outbox;

class GetOutboxMergeTagByOutbox extends OrgAction
{
    public function handle(Outbox $outbox): array
    {
        switch ($outbox->code) {
            case OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION:
                return OutboxMergeTagsEnum::filterTags([OutboxMergeTagsEnum::CUSTOMER_NAME, OutboxMergeTagsEnum::PRODUCTS]);
            default:
                return GetMailshotMergeTags::run();
        }
    }
}
