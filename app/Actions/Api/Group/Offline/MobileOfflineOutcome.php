<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline;

final readonly class MobileOfflineOutcome
{
    public const string APPLIED = 'applied';
    public const string PARTLY_APPLIED = 'partly_applied';
    public const string REFUSED = 'refused';
    public const string CONFLICT = 'conflict';

    public const string INSTRUCTION_NONE = 'none';
    public const string INSTRUCTION_PUT_BACK = 'put_back';
    public const string INSTRUCTION_UNPACK = 'unpack';
    public const string INSTRUCTION_KEEP_IN_TOTE = 'keep_in_tote';
    public const string INSTRUCTION_PICK_AGAIN = 'pick_again';
    public const string INSTRUCTION_RECOUNT = 'recount';
    public const string INSTRUCTION_CHECK_SHELF = 'check_shelf';

    /**
     * @param array<string, mixed>|null $serverSnapshot
     */
    private function __construct(
        public string $state,
        public ?string $reason,
        public string $instruction,
        public ?string $message,
        public ?float $requestedQuantity,
        public ?float $appliedQuantity,
        public bool $countedSince,
        public ?int $conflictingUserId,
        public ?array $serverSnapshot,
    ) {
    }

    public static function applied(?float $requestedQuantity = null, ?float $appliedQuantity = null, bool $countedSince = false, ?string $message = null): self
    {
        return new self(self::APPLIED, null, self::INSTRUCTION_NONE, $message, $requestedQuantity, $appliedQuantity ?? $requestedQuantity, $countedSince, null, null);
    }

    public static function partlyApplied(float $requestedQuantity, float $appliedQuantity, string $reason, string $instruction, string $message, bool $countedSince = false, ?int $conflictingUserId = null): self
    {
        return new self(self::PARTLY_APPLIED, $reason, $instruction, $message, $requestedQuantity, $appliedQuantity, $countedSince, $conflictingUserId, null);
    }

    /**
     * @param array<string, mixed>|null $serverSnapshot
     */
    public static function refused(string $reason, string $instruction, string $message, ?float $requestedQuantity = null, ?int $conflictingUserId = null, ?array $serverSnapshot = null): self
    {
        return new self(self::REFUSED, $reason, $instruction, $message, $requestedQuantity, 0.0, false, $conflictingUserId, $serverSnapshot);
    }

    /**
     * @param array<string, mixed> $serverSnapshot
     */
    public static function conflict(string $reason, string $message, array $serverSnapshot, ?int $conflictingUserId = null): self
    {
        return new self(self::CONFLICT, $reason, self::INSTRUCTION_RECOUNT, $message, null, null, false, $conflictingUserId, $serverSnapshot);
    }

    public function isOpen(): bool
    {
        return in_array($this->state, [self::REFUSED, self::CONFLICT, self::PARTLY_APPLIED], true);
    }

    public function blocksLaterActions(): bool
    {
        return in_array($this->state, [self::REFUSED, self::CONFLICT], true);
    }
}
