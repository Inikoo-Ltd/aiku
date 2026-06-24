<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 8 May 2026 09:30:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Traits;

use App\Models\Comms\DispatchedEmail;
use Illuminate\Support\Facades\Crypt;

trait WithDispatchedEmailEncryption
{
    /**
     * Encrypt and store the dispatched email ID in the data field
     *
     * @param DispatchedEmail $dispatchedEmail
     * @return DispatchedEmail
     */
    protected function encryptAndStoreDispatchedEmailId(DispatchedEmail $dispatchedEmail): DispatchedEmail
    {
        $encryptedId = Crypt::encrypt($dispatchedEmail->id);


        $existingData = $dispatchedEmail->data ?? [];

        $updatedData = array_merge($existingData, [
            'encrypted_id' => $encryptedId
        ]);

        $dispatchedEmail->update([
            'data' => $updatedData
        ]);

        return $dispatchedEmail;
    }
}
