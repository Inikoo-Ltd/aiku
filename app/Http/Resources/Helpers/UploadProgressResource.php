<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jun 2023 13:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Enums\UI\CRM\ProspectsTabsEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
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
        $upload     = $this->resource;
        $isFinished = $upload->number_success + $upload->number_fails >= $upload->number_rows;

        return [
            'action_type'       => 'Upload',
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
            'total'        => $upload->number_rows,//need*
            'done'         => $upload->number_success + $upload->number_fails,//need
            'data'         => [
                'type'           => $upload->model,
                'filename'       => $upload->filename,
                'number_success' => $upload->number_success,
                'number_fails'   => $upload->number_fails,
            ],
            'fail_reasons' => $isFinished ? $upload->failReasons() : [],
            'report_route' => $isFinished && $upload->number_fails > 0 ? $this->reportRoute($upload) : null,
            'show_route'   => $this->user instanceof \App\Models\CRM\WebUser
                ? ['name' => 'retina.helpers.uploads.records.show', 'parameters' => $upload->id]
                : ['name' => 'grp.helpers.uploads.records.show', 'parameters' => $upload->id],
        ];
    }

    /**
     * @return array{name: string, parameters: array<string, string>}|null
     */
    private function reportRoute(Upload $upload): ?array
    {
        if (!$this->user instanceof User || $upload->model !== class_basename(Prospect::class)) {
            return null;
        }

        $shop = $upload->parent;
        if (!$shop instanceof Shop) {
            return null;
        }

        return [
            'name'       => 'grp.org.shops.show.crm.prospects.index',
            'parameters' => [
                'organisation' => $shop->organisation->slug,
                'shop'         => $shop->slug,
                'tab'          => ProspectsTabsEnum::UPLOADS->value,
            ],
        ];
    }
}
