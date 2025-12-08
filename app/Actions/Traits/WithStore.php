<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:27:08 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Traits;

trait WithStore
{
    public function postStore($res, $model)
    {
        $res->model = $model;
        $res->model_id = $model->id;
        $res->status = $res->changes ? 'inserted' : 'unchanged';

        return $res;
    }
}
