<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketLinkTypeEnum: string
{
    use EnumHelperTrait;

    case RELATES = 'relates';
    case BLOCKS = 'blocks';
    case DUPLICATES = 'duplicates';

    /**
     * How the link reads from the ticket it starts on.
     */
    public function outwardLabel(): string
    {
        return match ($this) {
            self::RELATES    => __('Relates to'),
            self::BLOCKS     => __('Blocks'),
            self::DUPLICATES => __('Duplicates'),
        };
    }

    /**
     * How the same link reads from the other ticket.
     */
    public function inwardLabel(): string
    {
        return match ($this) {
            self::RELATES    => __('Relates to'),
            self::BLOCKS     => __('Is blocked by'),
            self::DUPLICATES => __('Is duplicated by'),
        };
    }

    /**
     * Choices offered when linking, each one a type read in one direction.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function choices(): array
    {
        return [
            ['value' => 'relates', 'label' => self::RELATES->outwardLabel()],
            ['value' => 'blocks', 'label' => self::BLOCKS->outwardLabel()],
            ['value' => 'blocked_by', 'label' => self::BLOCKS->inwardLabel()],
            ['value' => 'duplicates', 'label' => self::DUPLICATES->outwardLabel()],
            ['value' => 'duplicated_by', 'label' => self::DUPLICATES->inwardLabel()],
        ];
    }
}
