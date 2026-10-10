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

    public const int OUT_OF_BALANCE = 40200;

    public function __construct(string $message, public readonly ?int $statusCode = null)
    {
        parent::__construct($message, $statusCode ?? 0);
    }

    /**
     * Our monthly budget is spent or the DataForSEO account has no balance left: every further
     * billable call would fail the same way, so a run stops here.
     */
    public function isBudgetReached(): bool
    {
        return in_array($this->statusCode, [self::BUDGET_REACHED, self::OUT_OF_BALANCE], true);
    }
}
