<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 03 Dec 2025 11:28:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledTaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_name' => $this->task_name,
            'task_type' => $this->task_type,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'duration' => $this->duration,
            'status' => $this->status,
            'error_message' => $this->error_message,
        ];
    }
}
