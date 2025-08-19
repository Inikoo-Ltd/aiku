<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Feb 2024 14:55:12 Malaysia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Actions\Web\Webpage\WithGetWebpageWebBlocks;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Web\WebBlockParametersResource;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class SnapshotResource extends JsonResource
{
    use HasSelfCall;
    use WithGetWebpageWebBlocks;


    public function toArray($request): array
    {
        /** @var Snapshot $snapshot */
        $snapshot = $this;


        $comment = $snapshot->comment;

        if ($snapshot->first_commit) {
            $comment = __('First commit');
        }

        $publisher       = '';
        $publisherAvatar = null;
        if ($snapshot->publisher_id) {
            switch ($snapshot->publisher_type) {
                case 'WebUser':
                    /** @var \App\Models\CRM\WebUser $webUser */
                    $webUser = $snapshot->publisher;

                    $publisher       = $webUser->contact_name;
                    $publisherAvatar = $webUser->imageSources(48, 48);
                    break;
                case 'User':
                    /** @var User $user */
                    $user = $snapshot->publisher;

                    $publisher       = $user->contact_name;
                    $publisherAvatar = $user->imageSources(48, 48);
            }
        }
        $webPageLayout = null;
        $modelId = null;
        $webpage = null;
        if ($snapshot->parent_type == 'Webpage') {
            $webpage = $snapshot->parent;
            $webPageLayout = $snapshot->layout ?: ['web_blocks' => []];
            $webPageLayout['web_blocks'] = $this->getWebBlocks($webpage, Arr::get($webPageLayout, 'web_blocks'));
            $modelId = null;
            if ($webpage->model_type == 'Product') {
                $modelId = $webpage->model->family_id;
            } else {
                $modelId = $webpage->model_id;
            }
        }

        // dd($webPageLayout);

        return [
                'id'               => $snapshot->id,
                'parent_id'        => $snapshot->parent_id,
                'published_at'     => $snapshot->published_at,
                'published_until'  => $snapshot->published_until,
                'first_commit'     => $snapshot->first_commit,
                'recyclable'       => $snapshot->recyclable,
                'recyclable_tag'   => $snapshot->recyclable_tag,
                'layout'           => $webPageLayout ? $webPageLayout : $snapshot->layout,
                'model_id'              => $webpage ? $webpage->model_id : null,
                'product_category_id'   => $modelId,
                'publisher'        => $publisher,
                'publisher_avatar' => $publisherAvatar,
                'state'            => match ($snapshot->state) {
                    SnapshotStateEnum::LIVE => [
                        'tooltip' => __('live'),
                        'icon'    => 'fal fa-broadcast-tower',
                        'class'   => 'text-green-600 animate-pulse'
                    ],
                    SnapshotStateEnum::UNPUBLISHED => [
                        'tooltip' => __('unpublished'),
                        'icon'    => 'fal fa-seedling',
                        'class'   => 'text-indigo-500'
                    ],
                    SnapshotStateEnum::HISTORIC => [
                        'tooltip' => __('historic'),
                        'icon'    => 'fal fa-ghost'
                    ]
                },
                'comment'          => $comment,
                'label'            => $snapshot->label,
                'web_blocks_parameters'   => $webpage ?  WebBlockParametersResource::collection($webpage->webBlocks) : null,
            ];
        
    }
}
