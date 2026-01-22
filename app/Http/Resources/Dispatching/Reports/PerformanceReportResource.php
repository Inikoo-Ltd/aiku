<?php

namespace App\Http\Resources\Dispatching\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deliveries' => $this->deliveries,
            'items' => $this->items,
            'dp' => $this->dp,
            'hours' => round($this->hours, 2),
            'dp_per_hour' => round($this->dp_per_hour, 2),
            'issues' => $this->issues,
            'issues_percentage' => round($this->issues_percentage, 2),
            'cartons' => $this->cartons,
            'bonus' => $this->bonus,
            'salary' => $this->salary,
            'bonus_net' => $this->bonus_net,
            'dp_percentage' => round($this->dp_percentage, 2),
        ];
    }
}
