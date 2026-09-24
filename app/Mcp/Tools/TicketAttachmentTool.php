<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Models\Helpers\Media;
use App\Models\Helpers\Ticket;
use App\Models\Helpers\TicketComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

#[Description('Reads one attachment of a ticket. Pass the ticket reference and the attachment name or id as tickets-tool lists them. PDF, Word (docx), CSV and text files come back as extracted text; images come back as the image. Spreadsheets, archives and videos are not readable here. Same visibility rules as tickets-tool.')]
#[IsReadOnly]
class TicketAttachmentTool extends Tool
{
    private const int MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const int MAX_TEXT_CHARS = 200_000;

    public function shouldRegister(Request $request): bool
    {
        return Ticket::canUseAssistant($request->user());
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'reference'  => ['required', 'string'],
            'attachment' => ['required', 'string'],
        ]);

        $user   = $request->user();
        $ticket = Ticket::where('group_id', $user->group_id)->visibleTo($user)->where('reference', strtoupper($request->string('reference')))->first();
        if (!$ticket || !$ticket->canPreviewAttachmentsBy($user)) {
            return Response::error('Ticket not found or not visible to you.');
        }

        $wanted = $request->string('attachment')->toString();
        $media  = Media::whereIn('collection_name', ['ticket_images', 'ticket_attachments'])
            ->where(fn ($query) => $query->where('ulid', $wanted)->orWhere('name', $wanted))
            ->where(fn ($query) => $query
                ->where(fn ($ticketMedia) => $ticketMedia->where('model_type', $ticket->getMorphClass())->where('model_id', $ticket->id))
                ->orWhere(fn ($commentMedia) => $commentMedia->where('model_type', (new TicketComment())->getMorphClass())->whereIn('model_id', $ticket->comments()->select('id'))))
            ->orderByDesc('id')
            ->get()
            ->first(fn (Media $media) => $ticket->hasAttachmentVisibleTo($media, $user));

        if (!$media) {
            return Response::error('Attachment not found on this ticket.');
        }

        if ($media->size > self::MAX_FILE_BYTES) {
            return Response::error('Attachment is larger than 10 MB and cannot be read here.');
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();
        if (!$disk->exists($path)) {
            return Response::error('Attachment file is missing from storage.');
        }

        $content = $disk->get($path);

        if (str_starts_with((string) $media->mime_type, 'image/')) {
            return Response::image($content, $media->mime_type);
        }

        $extension = strtolower(pathinfo($media->name, PATHINFO_EXTENSION));
        $text      = match ($extension) {
            'pdf'        => $this->pdfText($content),
            'docx'       => $this->docxText($content),
            'csv', 'txt' => $content,
            default      => null,
        };

        if ($text === null) {
            return Response::error("Cannot extract text from .$extension files.");
        }

        $text = trim($text);
        if ($text === '') {
            return Response::error('No text found in the attachment, it may be a scanned document.');
        }

        return Response::json([
            'name'      => $media->name,
            'mime'      => $media->mime_type,
            'size'      => $media->size,
            'truncated' => mb_strlen($text) > self::MAX_TEXT_CHARS,
            'text'      => mb_substr($text, 0, self::MAX_TEXT_CHARS),
        ]);
    }

    private function pdfText(string $content): string
    {
        try {
            return (new PdfParser())->parseContent($content)->getText();
        } catch (Throwable) {
            return '';
        }
    }

    private function docxText(string $content): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'ticket_docx_');
        file_put_contents($temporaryPath, $content);

        try {
            $zip = new ZipArchive();
            if ($zip->open($temporaryPath, ZipArchive::RDONLY) !== true) {
                return '';
            }
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();

            return html_entity_decode(strip_tags(preg_replace(['/<\/w:p>/', '/<w:tab\/>/', '/<w:br\/>/'], ["\n", "\t", "\n"], $xml)));
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference'  => $schema->string()->description('Ticket reference, e.g. HELP-3213')->required(),
            'attachment' => $schema->string()->description('Attachment file name or id (the last part of its url from tickets-tool)')->required(),
        ];
    }
}
