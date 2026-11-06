<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteCrawlsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'website_domain' => $this->website_domain,
            'website_slug'   => $this->website_slug,
            'state'          => $this->state,
            'finish_reason'  => $this->finish_reason,
            'trigger'        => $this->trigger,
            'start_at'       => $this->start_at,
            'end_at'         => $this->end_at,
            'urls_processed' => (int) $this->urls_processed,
            'urls_found'     => (int) $this->urls_found,
            'health_score'   => $this->health_score === null ? null : (float) $this->health_score,
            'errors'         => (int) $this->number_errors,
            'warnings'       => (int) $this->number_warnings,
            'notices'        => (int) $this->number_notices,
            'user_name'      => $this->user_name,
            'is_full_audit'  => (bool) $this->is_full_audit,
        ];
    }
}
