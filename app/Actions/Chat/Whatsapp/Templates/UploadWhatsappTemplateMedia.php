<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Models\SysAdmin\Organisation;
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

    public function handle(UploadedFile $file, string $accessToken, ?Organisation $organisation = null): ?string
    {
        return $this->fromPath($file->getPathname(), (string) $file->getMimeType(), $accessToken, $organisation);
    }

    /**
     * A draft's sample file is already stored in Aiku, so it reaches Meta from disk rather
     * than from an upload that is no longer in flight.
     */
    public function fromPath(string $path, string $mimeType, string $accessToken, ?Organisation $organisation = null): ?string
    {
        ['app_id' => $appId] = $this->metaAppCredentials($organisation);

        if ($appId === '') {
            Log::warning('WhatsApp template media upload needs the Meta App ID in the organisation settings', [
                'organisation' => $organisation?->slug,
            ]);

            return null;
        }

        $session = Http::withToken($accessToken)->post($this->whatsappEndpoint($appId.'/uploads'), [
            'file_length' => filesize($path),
            'file_type'   => $mimeType,
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
            'Content-Type'  => $mimeType,
        ])->withBody(
            file_get_contents($path),
            $mimeType
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
