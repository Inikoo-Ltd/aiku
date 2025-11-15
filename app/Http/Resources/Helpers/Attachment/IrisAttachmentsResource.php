<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Helpers\Attachment;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $caption
 * @property mixed $scope
 * @property mixed $media_id
 * @property mixed $media_ulid
 * @property mixed $mime_type
 */
class IrisAttachmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'caption'        => $this->caption,
            'scope'          => $this->scope,
            'label'          => TradeAttachmentScopeEnum::labels()[$this->scope],
            'media_id'       => $this->media_id,
            'media_ulid'     => $this->media_ulid,
            'mime_type'      => $this->mime_type,
            'download_route' => [
                'name'       => 'iris.iris_attachment',
                'parameters' => ['media' => $this->media_ulid],
                'method'     => 'get'
            ]

        ];
    }
}
