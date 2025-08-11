<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jun 2023 13:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadProgressResource extends JsonResource
{
    use HasSelfCall;

    public User|WebUser $user;
    public function __construct($resource, User|WebUser $user)
    {
        parent::__construct($resource);
        $this->user = $user;
    }

    public function toArray($request): array
    {
        /** @var Upload $upload */
        $upload = $this;

        return [
            'action_type'  => 'Upload',
            'action_id'         => $upload->id,
            'id'                => $upload->id,
            'type'              => $upload->model,
            'original_filename' => $upload->original_filename,
            'filename'          => $upload->filename,
            'number_rows'       => $upload->number_rows,//need
            'number_success'    => $upload->number_success,//need
            'number_fails'      => $upload->number_fails,//need
            'path'              => $upload->path,
            'start_at'     => $upload->created_at,
            'end_at'       => $upload->uploaded_at,
            'last_updated' => $upload->updated_at,
            'total'        => $upload->number_rows,//need
            'done'         => $upload->number_success + $upload->number_fails,//need
            'data'         => [
                'type'           => $upload->model,
                'filename'       => $upload->filename,
                'number_success' => $upload->number_success,
                'number_fails'   => $upload->number_fails,

            ],


        ];
    }
}
