<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 11:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A file the supplier sent with one of its own submissions, to view or download again from its portal. Only the
 * files kept on that submission are reachable, never other attachments of the same media.
 */
class DownloadSupplierPortalSubmissionFile
{
    use AsAction;

    public function handle(Supplier $supplier, int $submissionId, Media $media, bool $inline = false): BinaryFileResponse
    {
        /** @var SupplierSubmission $submission */
        $submission = SupplierSubmission::where('supplier_id', $supplier->id)->findOrFail($submissionId);
        abort_unless($submission->attachments()->where('media.id', $media->id)->exists(), 404);

        return DownloadAttachment::make()->handle($media, $inline);
    }

    public function asController(string $token, int $submission, Media $media, Request $request): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier, $submission, $media, $request->boolean('inline'));
    }
}
