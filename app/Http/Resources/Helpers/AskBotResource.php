<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Http\Resources\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

class AskBotResource extends JsonResource
{
    public function toArray($request)
    {
        if (isset($this['error'])) {
            return [
                'error' => $this['error'],
            ];
        }

        return [
            'response' => $this['response'],
        ];
    }
}
