<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * Why a conversation was put aside. A fixed list rather than free writing: clearing an imported
 * mailbox is a bulk job, done dozens of times an hour, and anything that has to be typed becomes
 * blank or inconsistent within a day. Chosen from a list it can be counted, so after a week the
 * noise can be named and dealt with where it comes from instead of one conversation at a time.
 *
 * Nothing here is shown to the customer. It is ours, about our own queue.
 */
enum ChatIgnoreReasonEnum: string
{
    use EnumHelperTrait;

    /** An automatic reply. The customer is away and has not asked for anything. */
    case OUT_OF_OFFICE = 'out_of_office';

    /** A newsletter or a campaign, sent to a list this mailbox happens to be on. */
    case MARKETING = 'marketing';

    /** A supplier or another of our own shops writing to everybody at once. */
    case SUPPLIER_CIRCULAR = 'supplier_circular';

    /** A machine reporting something: a receipt, an alert, a delivery notice. */
    case AUTOMATED_NOTIFICATION = 'automated_notification';

    /** Meant for somebody else, or nothing to do with us. */
    case NOT_FOR_US = 'not_for_us';

    public static function labels(): array
    {
        return [
            'out_of_office'          => __('Out of office'),
            'marketing'              => __('Newsletter or marketing'),
            'supplier_circular'      => __('Supplier circular'),
            'automated_notification' => __('Automated notification'),
            'not_for_us'             => __('Not for us'),
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
