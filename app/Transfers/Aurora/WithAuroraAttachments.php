<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Helpers\Media\DeleteAttachment;
use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Models\CRM\Customer;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mimey\MimeTypes;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Throwable;

trait WithAuroraAttachments
{
    public function getModelAttachmentsCollection($model, $id): Collection
    {
        return DB::connection('aurora')
            ->table('Attachment Bridge as B')
            ->leftJoin('Attachment Dimension as A', 'A.Attachment Key', '=', 'B.Attachment Key')
            ->where('Subject', $model)
            ->where('Subject Key', $id)
            ->get();
    }

    public function fetchAttachment($auroraAttachmentData, $organisationID): ?array
    {
        if ($auroraAttachmentData->{'Attachment Data'} === null) {
            return null;
        }

        $content = $auroraAttachmentData->{'Attachment Data'};

        $temporaryDirectory = TemporaryDirectory::make();

        $mimes = new MimeTypes();


        $temporalName = $auroraAttachmentData->{'Attachment Key'}.'.'.$auroraAttachmentData->{'Attachment File Checksum'};

        $extension = $mimes->getExtension($auroraAttachmentData->{'Attachment MIME Type'});

        if ($extension) {
            $temporalName .= '.'.$extension;
        }


        file_put_contents($temporaryDirectory->path($temporalName), $content);


        return [
            'temporaryDirectory' => $temporaryDirectory,
            'is_public'          => $auroraAttachmentData->{'Attachment Public'} == 'Yes',
            'modelData'          => [
                'path'            => $temporaryDirectory->path($temporalName),
                'originalName'    => $auroraAttachmentData->{'Attachment File Original Name'},
                'scope'           => $auroraAttachmentData->{'Attachment Subject Type'},
                'caption'         => $auroraAttachmentData->{'Attachment Caption'},
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
                'source_id'       => $organisationID.':'.$auroraAttachmentData->{'Attachment Bridge Key'},

            ]
        ];
    }

    protected function processFetchAttachments(Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|null $model, string $modelType, string $modelSourceID): void
    {
        if (!$model) {
            return;
        }
        $attachmentModelType = class_basename($model);

        $delete = true;
        if (in_array($attachmentModelType, ['Supplier', 'SupplierProduct', 'Agent', 'Stock', 'TradeUnit'])) {
            $delete = false;
        }

        $attachmentsToDelete = $model->attachments()->pluck('source_id', 'model_has_attachments.id')->all();


        foreach ($this->parseAttachments($modelSourceID, $modelType) as $attachmentData) {
            if ($attachmentData === null) {
                continue;
            }

            $media = SaveModelAttachment::make()->action(
                model: $model,
                modelData: $attachmentData['modelData'],
                hydratorsDelay: 30,
                strict: false
            );

            $modelAttachment = $model->attachments()->where('media_id', $media->id)->first();


            $sources = json_decode($modelAttachment->pivot->sources, true);

            $bridgeSources     = Arr::get($sources, 'bridge', []);
            $bridgeSources[]   = $attachmentData['modelData']['source_id'];
            $bridgeSources     = array_unique($bridgeSources);
            $sources['bridge'] = $bridgeSources;

            $modelSources                  = Arr::get($sources, $attachmentModelType, []);
            $modelSources[]                = $model->source_id;
            $modelSources                  = array_unique($modelSources);
            $sources[$attachmentModelType] = $modelSources;

            $model->attachments()->updateExistingPivot(
                $media->id,
                [
                    "sources" =>
                        json_encode($sources)

                ]
            );


            $attachmentsToDelete = array_diff($attachmentsToDelete, [$attachmentData['modelData']['source_id']]);
            if ($delete) {
                $attachmentData['temporaryDirectory']->delete();
            }
        }
        if ($delete) {
            foreach ($attachmentsToDelete as $attachmentSourceID) {
                $modelHasAttachment = DB::table('model_has_attachments')->where('source_id', $attachmentSourceID)->first();
                /** @var Media $attachment */
                $attachment = Media::find($modelHasAttachment->media_id);
                DetachAttachmentFromModel::make()->action($model, $attachment);
                try {
                    DeleteAttachment::make()->action($attachment);
                } catch (Throwable) {
                    //do nothing
                }
            }
        }
    }

    protected function parseAttachments($modelSource, $auroraModelName): array
    {
        $modelSourceData = explode(':', $modelSource);
        $attachments     = $this->getModelAttachmentsCollection(
            $auroraModelName,
            $modelSourceData[1]
        )->map(function ($auroraAttachment) use ($modelSourceData) {
            return $this->fetchAttachment($auroraAttachment, $modelSourceData[0]);
        });

        return $attachments->toArray();
    }
}
