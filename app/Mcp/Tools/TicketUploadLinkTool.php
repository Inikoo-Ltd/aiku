<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Gives a one-use upload link for attaching a local file (a screenshot, a PDF) to a ticket comment without passing its bytes through the tool call. Upload the file with: curl -sf -F file=@/path/to/file.png "<upload_url>". The link works for 30 minutes and takes one file (images, PDF, Word, Excel, CSV, video, archives; 50 MB). Then pass the upload_id in uploads on ticket-write-tool.')]
class TicketUploadLinkTool extends Tool
{
    public function shouldRegister(Request $request): bool
    {
        return $request->user() !== null;
    }

    public function handle(Request $request): Response
    {
        $uploadId = (string) Str::ulid();

        return Response::json([
            'upload_id'  => $uploadId,
            'upload_url' => URL::temporarySignedRoute('mcp.ticket_upload', now()->addMinutes(30), ['user' => $request->user()->id, 'upload' => $uploadId]),
            'curl'       => 'curl -sf -F file=@/path/to/file "<upload_url>"',
            'expires_in_minutes' => 30,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
