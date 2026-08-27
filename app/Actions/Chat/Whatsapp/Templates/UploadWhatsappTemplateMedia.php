<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A template with a media header needs a sample file for the reviewer, and that file is
 * referenced by a handle rather than uploaded with the template. Meta hands the handle
 * out through its resumable upload API, which is a two-step dance: open a session, then
 * push the bytes into it.
 */
class UploadWhatsappTemplateMedia
{
    use AsAction;
    use WithWhatsappCredentials;

    public function handle(UploadedFile $file, string $accessToken): ?string
    {
        $appId = (string) config('meta.whatsapp.app_id');

        if ($appId === '') {
            Log::warning('WhatsApp template media upload needs WHATSAPP_APP_ID to be configured');

            return null;
        }

        $session = Http::withToken($accessToken)->post($this->whatsappEndpoint($appId.'/uploads'), [
            'file_length' => $file->getSize(),
            'file_type'   => $file->getMimeType(),
        ]);

        $sessionId = $session->json('id');

        if ($session->failed() || blank($sessionId)) {
            Log::warning('WhatsApp upload session could not be opened', [
                'status' => $session->status(),
                'body'   => $session->json(),
            ]);

            return null;
        }

        // The upload call authenticates with an OAuth header rather than a bearer token,
        // and the body is the raw file, so it cannot go through Http::attach().
        $upload = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
            'file_offset'   => '0',
            'Content-Type'  => $file->getMimeType(),
        ])->withBody(
            file_get_contents($file->getPathname()),
            $file->getMimeType()
        )->post($this->whatsappEndpoint($sessionId));

        $handle = $upload->json('h');

        if ($upload->failed() || blank($handle)) {
            Log::warning('WhatsApp sample file upload failed', [
                'status' => $upload->status(),
                'body'   => $upload->json(),
            ]);

            return null;
        }

        return $handle;
    }
}
