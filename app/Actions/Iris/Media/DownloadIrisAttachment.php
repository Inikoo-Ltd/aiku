<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Media;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Helpers\Media;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadIrisAttachment
{
    use AsAction;

    public const array PUBLIC_DOCUMENT_SCOPES = [
        TradeAttachmentScopeEnum::IFRA->value,
        TradeAttachmentScopeEnum::SDS->value,
        TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS->value,
        TradeAttachmentScopeEnum::DOC->value,
        TradeAttachmentScopeEnum::CPSR->value,
        TradeAttachmentScopeEnum::TEST_REPORTS->value,
    ];

    public function handle(Media $media): BinaryFileResponse
    {
        $filename = $media->media_scope == 'labeling_guide' ? $media->name : $media->file_name;

        return response()->download($media->getPath(), $filename);
    }

    public function asController(Media $media): BinaryFileResponse
    {
        if (!self::isPublic($media)) {
            abort(404);
        }

        return $this->handle($media);
    }

    public static function isPublic(Media $media): bool
    {
        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereIn('model_type', ['Product', 'TradeUnit', 'TradeUnitFamily'])
                        ->whereIn('scope', self::PUBLIC_DOCUMENT_SCOPES);
                })->orWhere(function (Builder $query) {
                    $query->whereIn('model_type', ['ProductCategory', 'TradeUnitFamily'])
                        ->where('scope', 'labeling_guide');
                });
            })
            ->exists();
    }
}
