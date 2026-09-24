<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\GoogleAds;

use Exception;
use Throwable;

/**
 * Carries the failure Google reported rather than the HTTP status alone, because the status is nearly
 * always 400 or 403 and says nothing about which of a dozen causes applies: an unapproved developer
 * token, a customer id that belongs to another manager, a budget below the account minimum, a policy
 * refusal on an ad. `$errorCode` is Google's own enum name for the failure, which is what a caller
 * needs when it wants to react to one specific cause instead of reporting the text.
 */
class GoogleAdsException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?int $status = null,
        public readonly array $failures = [],

        /**
         * Google's own path to the offending value, e.g.
         * `mutate_operations[5].ad_group_ad_operation.create.ad.responsive_search_ad.headlines[1]`.
         * Kept apart from the message so a caller can put the failure on the right form field, rather
         * than showing the reader a path through an API they have never heard of.
         */
        public readonly ?string $fieldPath = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    /** Message and field path together, for a log entry where nobody is around to ask. */
    public function describe(): string
    {
        return $this->fieldPath === null
            ? $this->getMessage()
            : $this->getMessage().' ('.$this->fieldPath.')';
    }

    public function isAuthorisation(): bool
    {
        return in_array($this->errorCode, [
            'DEVELOPER_TOKEN_NOT_APPROVED',
            'DEVELOPER_TOKEN_PROHIBITED',
            'CUSTOMER_NOT_ENABLED',
            'NOT_ADS_USER',
            'USER_PERMISSION_DENIED',
        ], true);
    }
}
