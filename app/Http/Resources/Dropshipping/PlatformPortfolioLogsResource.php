<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 16:46:29 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dropshipping;

use App\Helpers\PlatformResponseFormatter;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 * @property mixed $created_at
 * @property mixed $type
 * @property mixed $status
 * @property mixed $response
 * @property mixed $platform_id
 * @property mixed $platform_type
 * @property mixed $portfolio_id
 * @property mixed $item_code
 * @property mixed $platform_name
 */
class PlatformPortfolioLogsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $response = PlatformResponseFormatter::make()->format($this->response);

        return [
            'id'              => $this->id,
            'created_at'      => $this->created_at,
            'type'            => $this->type,
            'status'          => $this->status,
            'response'        => Arr::get($response, 'message'),
            'response_hint'   => Arr::get($response, 'hint'),
            'response_code'   => Arr::get($response, 'code'),
            'response_detail' => Arr::get($response, 'detail'),
            'platform_id'     => $this->platform_id,
            'platform_type'   => $this->platform_type,
            'platform_name'   => $this->platform_name,
            'portfolio_id'    => $this->portfolio_id,
            'item_code'       => $this->item_code,
        ];
    }
}
