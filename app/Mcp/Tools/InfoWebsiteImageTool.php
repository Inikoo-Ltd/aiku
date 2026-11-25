<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Helpers\Ticket\StoreTicketUpload;
use App\Actions\Web\StaticWebsite\StoreStaticWebsiteImage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Adds an image to an info website\'s own files so its pages can show it: pass the image the user gave you in the chat as image (JPG, PNG or WebP, up to 20 MB), or, from a client that can run curl (Claude Code), a local file uploaded to the link from ticket-upload-link-tool as upload_id, or an https address of an image hosted elsewhere as image_url. It is stored as an optimised WebP (at most 2400 px on its longest side) and returns src, a /_assets/... address, with its width and height. No page shows it until you use src in an edit with info-website-preview-tool (an <img src="..." alt="..." width height>, or url(...) in a hero background); the preview_url then shows the image, and the user confirms as for any change. Only for group administrators and group webmasters.')]
class InfoWebsiteImageTool extends AikuInfoWebsiteTool
{
    protected ?array $meta = ['openai/fileParams' => ['image']];

    public function handle(Request $request): Response
    {
        $request->validate([
            'website'            => ['required', 'string'],
            'image'              => ['sometimes', 'nullable', 'array'],
            'image.download_url' => ['required_with:image', 'string'],
            'image.file_name'    => ['sometimes', 'nullable', 'string'],
            'image_url'          => ['sometimes', 'nullable', 'string'],
            'upload_id'          => ['sometimes', 'nullable', 'string'],
        ]);

        $denied = $this->denied($request);
        if ($denied) {
            return $denied;
        }

        $staticWebsite = $this->staticWebsite($request);
        if ($staticWebsite instanceof Response) {
            return $staticWebsite;
        }

        $uploadId = (string) $request->get('upload_id');
        $upload   = $uploadId === '' ? null : StoreTicketUpload::find($request->user()->id, $uploadId);
        if ($uploadId !== '' && !$upload) {
            return Response::error("No file uploaded for $uploadId. Upload it to the link from ticket-upload-link-tool first, as the same user.");
        }
        $url = data_get($request->all(), 'image.download_url') ?: (string) $request->get('image_url');
        if (!$upload && $url === '') {
            return Response::error('Pass image, upload_id or image_url. Nothing was stored.');
        }

        try {
            $action = StoreStaticWebsiteImage::make();
            $image  = $upload
                ? $action->handle($staticWebsite, $upload->getContent(), $upload->getClientOriginalName())
                : $action->handle($staticWebsite, $action->download($url), data_get($request->all(), 'image.file_name') ?: (string) parse_url($url, PHP_URL_PATH));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was stored.');
        }

        if ($upload) {
            StoreTicketUpload::forget($request->user()->id, $uploadId);
        }

        return Response::json([
            ...$image,
            'website'  => $staticWebsite->domain,
            'next'     => 'Use src in the page HTML through info-website-preview-tool, then show the user the preview_url so they see the image before confirming.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'website'   => $schema->string()->description('Domain or slug of the info website')->required(),
            'image'     => $schema->object([
                'download_url' => $schema->string()->required(),
                'file_id'      => $schema->string()->required(),
                'mime_type'    => $schema->string(),
                'file_name'    => $schema->string(),
            ])->description('The image file the user uploaded in the chat'),
            'upload_id' => $schema->string()->description('Instead of image: upload_id of a local file sent with curl to the link from ticket-upload-link-tool'),
            'image_url' => $schema->string()->description('Instead of image: https address of an image hosted elsewhere, to copy into the website'),
        ];
    }
}
