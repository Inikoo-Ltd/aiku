<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class CrawlsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $stateValue = $this->state instanceof CrawlStateEnum ? $this->state->value : $this->state;

        return [
            'id'             => $this->id,
            'state'          => $stateValue,
            'state_icon'     => CrawlStateEnum::stateIcon()[$stateValue] ?? null,
            'state_label'    => CrawlStateEnum::labels()[$stateValue] ?? $stateValue,
            'trigger'        => $this->trigger,
            'type'           => $this->type,
            'running'        => $this->running,
            'finish_reason'  => $this->finish_reason,
            'start_at'       => $this->start_at,
            'end_at'         => $this->end_at,
            'urls_processed' => $this->urls_processed,
            'urls_found'     => $this->urls_found,
            'depth'          => $this->depth,
            'concurrency'    => $this->concurrency,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
