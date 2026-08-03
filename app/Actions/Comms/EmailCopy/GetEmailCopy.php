<?php

/*
 * Created Date: Wednesday, June 3rd 2026, 3:19:33 pm
 * Author: ekayudinata
 *
 * Copyright (c) 2026 Your Company
 */

namespace App\Actions\Comms\EmailCopy;

use App\Models\Comms\DispatchedEmail;
use Lorisleiva\Actions\Concerns\AsAction;

class GetEmailCopy
{
    use AsAction;

    public function handle(DispatchedEmail $dispatchedEmail): ?array
    {
        $emailCopy = $dispatchedEmail->emailCopy;

        if (!$emailCopy) {
            return null;
        }

        return [
            'id'              => $emailCopy->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'subject'         => $emailCopy->subject,
            'is_body_encoded' => $emailCopy->is_body_encoded,
            'created_at'      => $emailCopy->created_at,
            'updated_at'      => $emailCopy->updated_at,
            'body_preview' => $emailCopy->is_body_encoded ? $this->decodeBodySafely($emailCopy->body) : $emailCopy->body,
        ];
    }

    public function asController(DispatchedEmail $dispatchedEmail): ?array
    {
        return $this->handle($dispatchedEmail);
    }

    private function decodeBodySafely($body): string
    {
        try {
            $decoded = base64_decode($body, true);
            if ($decoded === false) {
                return '[Decode Error]';
            }

            // Check if the decoded string is valid UTF-8
            if (!mb_check_encoding($decoded, 'UTF-8')) {
                // Try to fix encoding issues
                $decoded = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
                if (!mb_check_encoding($decoded, 'UTF-8')) {
                    return '[Encoding Error]';
                }
            }

            return $decoded;
        } catch (\Exception $e) {
            return '[Decode Error]';
        }
    }
}
