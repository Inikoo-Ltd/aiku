<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-11h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Exceptions;

use Exception;

class FcmMessageRejected extends Exception
{
    public function __construct(public readonly int $status, public readonly ?string $errorCode, public readonly int $fcmTokenId)
    {
        parent::__construct("Firebase refused the push to fcm_tokens.id $fcmTokenId (HTTP $status, ".($errorCode ?? 'no error code').').');
    }

    /**
     * @return array{status: int, error_code: string|null, fcm_token_id: int}
     */
    public function context(): array
    {
        return [
            'status'       => $this->status,
            'error_code'   => $this->errorCode,
            'fcm_token_id' => $this->fcmTokenId,
        ];
    }
}
