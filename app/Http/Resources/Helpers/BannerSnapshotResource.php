<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 May 2026 14:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\Snapshot;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerSnapshotResource extends JsonResource
{
    use HasSelfCall;

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
            /** @var User $user */
            $user = $snapshot->publisher;

            $publisher       = $user->contact_name;
            $publisherAvatar = $user->imageSources(48, 48);
        }


        return [
            'id'              => $snapshot->id,
            'parent_id'       => $snapshot->parent_id,
            'published_at'    => $snapshot->published_at,
            'published_until' => $snapshot->published_until,
            'first_commit'    => $snapshot->first_commit,
            'recyclable'      => $snapshot->recyclable,
            'recyclable_tag'  => $snapshot->recyclable_tag,

            'publisher'        => $publisher,
            'publisher_avatar' => $publisherAvatar,
            'state_value'      => $snapshot->state->value,
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

        ];
    }
}
