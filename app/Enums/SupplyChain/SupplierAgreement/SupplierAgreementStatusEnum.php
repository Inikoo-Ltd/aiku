<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierAgreement;

use App\Enums\EnumHelperTrait;

/**
 * Where a supplier stands with the group's Supplier Agreement: it accepted a version that still counts, it accepted
 * one that a later version asked to accept again, or it never accepted any.
 */
enum SupplierAgreementStatusEnum: string
{
    use EnumHelperTrait;

    case ACCEPTED = 'accepted';
    case OUTDATED = 'outdated';
    case NOT_ACCEPTED = 'not_accepted';

    public static function labels(): array
    {
        return [
            'accepted'     => __('Accepted'),
            'outdated'     => __('Outdated'),
            'not_accepted' => __('Not accepted'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    public function colour(): string
    {
        return match ($this) {
            self::ACCEPTED     => 'green',
            self::OUTDATED     => 'amber',
            self::NOT_ACCEPTED => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACCEPTED     => 'fal fa-file-signature',
            self::OUTDATED     => 'fal fa-file-exclamation',
            self::NOT_ACCEPTED => 'fal fa-file-times',
        };
    }
}
