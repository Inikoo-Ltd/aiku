<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Enums\GoodsIn\Sowing\SowingTypeEnum;

class ReturnNotReturnedOfflineHandler extends ReturnPutOfflineHandler
{
    public function operation(): string
    {
        return 'return_not_returned';
    }

    protected function sowingType(): SowingTypeEnum
    {
        return SowingTypeEnum::NOT_SOW;
    }
}
