<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Dispatching;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentsResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'name'               => $this->shipper->name,
            'reference'          => $this->reference,
            'tracking'          => $this->tracking,
            'trackings'          => $this->trackings,
            'tracking_urls'     => $this->tracking_urls,
            'tracking_url'        => $this->shipper->tracking_url,
            'combined_label_url' => $this->combined_label_url,
            'pdf_label'         => $this->pdf_label,
        ];
    }
}
