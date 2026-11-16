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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadIrisAttachment
{
    use AsAction;

    public function handle(Media $media): BinaryFileResponse
    {
        $filename = in_array($media->media_scope, ['labeling_guide', 'webpage']) ? $media->name : $media->file_name;

        return response()->download($media->getPath(), $filename);
    }

    public function asController(Media $media): BinaryFileResponse|RedirectResponse
    {
        if (!self::isPublic($media) && $currentDocument = self::currentDocumentReplacing($media)) {
            return redirect('/attachment/'.$currentDocument->ulid.'/download');
        }

        if (!self::isPublic($media) || (!auth('retina')->check() && !self::isWebpageAttachment($media) && !self::isPublicProductDocument($media))) {
            abort(404);
        }

        return $this->handle($media);
    }

    /**
     * Links to a product document live on in dropshippers' store descriptions after staff replace it (PRE-101), so they lead to the document now holding its place, when every place it was taken from points to the same one
     */
    public static function currentDocumentReplacing(Media $media): ?Media
    {
        $replacements = collect($media->getCustomProperty('detached_from', []))
            ->flatMap(fn (array $detachedFrom) => DB::table('model_has_attachments')
                ->where('model_type', $detachedFrom['model_type'])
                ->where('model_id', $detachedFrom['model_id'])
                ->where('scope', $detachedFrom['scope'])
                ->where(fn (Builder $query) => self::wherePublicProductDocument($query))
                ->pluck('media_id'))
            ->unique();

        $currentDocuments = Media::whereIn('id', $replacements)->where('id', '!=', $media->id)->get();
        if ($currentDocuments->pluck('checksum')->unique()->count() !== 1) {
            return null;
        }

        return $currentDocuments->sortByDesc('id')->first();
    }

    public static function isWebpageAttachment(Media $media): bool
    {
        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->where('model_type', 'Webpage')
            ->where('scope', 'webpage')
            ->exists();
    }

    public static function isPublic(Media $media): bool
    {
        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->where(function (Builder $query) {
                $query->where(fn (Builder $query) => self::wherePublicProductDocument($query))
                    ->orWhere(function (Builder $query) {
                        $query->whereIn('model_type', ['ProductCategory', 'TradeUnitFamily'])
                            ->where('scope', 'labeling_guide');
                    })->orWhere(function (Builder $query) {
                        $query->where('model_type', 'Webpage')
                            ->where('scope', 'webpage');
                    });
            })
            ->exists();
    }

    /**
     * Product documents are linked from the descriptions aiku pushes into dropshippers' Shopify and WooCommerce stores, whose shoppers are never logged in to the website (PRE-67)
     */
    public static function isPublicProductDocument(Media $media): bool
    {
        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->where(fn (Builder $query) => self::wherePublicProductDocument($query))
            ->exists();
    }

    private static function wherePublicProductDocument(Builder $query): Builder
    {
        return $query->whereIn('model_type', ['Product', 'TradeUnit', 'TradeUnitFamily'])
            ->whereIn('scope', TradeAttachmentScopeEnum::publicScopes())
            ->whereNot(function (Builder $query) {
                $query->where('model_type', 'TradeUnit')
                    ->whereIn('scope', TradeAttachmentScopeEnum::substanceScopes())
                    ->whereIn('model_id', DB::table('trade_units')->select('id')->whereNotNull('documents_trade_unit_id'));
            });
    }
}
