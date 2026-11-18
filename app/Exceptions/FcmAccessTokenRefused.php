<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-11h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Exceptions;

use Exception;

class FcmAccessTokenRefused extends Exception
{
    public function __construct(public readonly ?int $status, public readonly ?string $errorCode, public readonly bool $isTemporary)
    {
        parent::__construct('Firebase credentials are set but the access token request failed (HTTP '.($status ?? 'none').', '.($errorCode ?? 'no error code').').');
    }

    /**
     * @return array{status: int|null, error_code: string|null, is_temporary: bool}
     */
    public function context(): array
    {
        return [
            'status'       => $this->status,
            'error_code'   => $this->errorCode,
            'is_temporary' => $this->isTemporary,
        ];
    }
}
