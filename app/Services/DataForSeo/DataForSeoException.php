<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\DataForSeo;

use Exception;

/**
 * `$statusCode` is DataForSEO's own five digit code (40200 out of balance, 40501 invalid field, ...),
 * which says far more than the HTTP status, nearly always 200.
 */
class DataForSeoException extends Exception
{
    public const int BUDGET_REACHED = 1;

    public function __construct(string $message, public readonly ?int $statusCode = null)
    {
        parent::__construct($message, $statusCode ?? 0);
    }

    public function isBudgetReached(): bool
    {
        return $this->statusCode === self::BUDGET_REACHED;
    }
}
