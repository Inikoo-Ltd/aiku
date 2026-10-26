<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierSubmission;

use App\Enums\EnumHelperTrait;

enum SupplierSubmissionStateEnum: string
{
    use EnumHelperTrait;

    case DRAFT = 'draft';
    case RECEIVED = 'received';
    case UNDER_REVIEW = 'under_review';
    case MORE_INFORMATION_REQUESTED = 'more_information_requested';
    case ACCEPTED = 'accepted';
    case NOT_PROCEEDING = 'not_proceeding';

    public static function labels(): array
    {
        return [
            'draft'                      => __('Draft'),
            'received'                   => __('Received'),
            'under_review'               => __('Under review'),
            'more_information_requested' => __('More information requested'),
            'accepted'                   => __('Accepted'),
            'not_proceeding'             => __('Not proceeding'),
        ];
    }

    /**
     * The supplier can still change a submission while it is a draft or after we asked for more.
     */
    public function isEditableBySupplier(): bool
    {
        return in_array($this, [self::DRAFT, self::MORE_INFORMATION_REQUESTED], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::RECEIVED, self::UNDER_REVIEW], true);
    }
}
