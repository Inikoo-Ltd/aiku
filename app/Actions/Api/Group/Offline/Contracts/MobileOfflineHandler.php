<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Contracts;

use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;

interface MobileOfflineHandler
{
    public function operation(): string;

    public function kind(): string;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validatePayload(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int, v?: int}>
     */
    public function touchedRows(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome;

    public function canRollback(MobileOfflineOperation $operation): bool;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function rollback(MobileOfflineOperation $operation, User $user): void;
}
