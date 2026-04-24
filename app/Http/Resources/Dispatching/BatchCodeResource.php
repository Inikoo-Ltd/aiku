<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $expiry_date
 */
class BatchCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'expiry_date'    => $this->expiry_date?->format('d M Y'),
            'label'          => $this->code.($this->expiry_date ? ' — exp: '.$this->expiry_date->format('d M Y') : ''),
            'org_stock_id'   => $this->org_stock_id,
            'org_stock_code' => $this->orgStock?->code,
            'org_stock_name' => $this->orgStock?->name,
            'org_stock_slug' => $this->orgStock?->slug,
            'routes'         => [
                'show'   => [
                    'name'       => 'grp.org.warehouses.show.inventory.batch_codes.show',
                    'parameters' => [$request->route('organisation')?->slug, $request->route('warehouse')?->slug, $this->id],
                ],
                'edit'   => [
                    'name'       => 'grp.org.warehouses.show.inventory.batch_codes.edit',
                    'parameters' => [$request->route('organisation')?->slug, $request->route('warehouse')?->slug, $this->id],
                ],
                'delete' => [
                    'name'       => 'grp.models.batch_code.delete',
                    'parameters' => ['batchCode' => $this->id],
                ],
            ],
        ];
    }
}
