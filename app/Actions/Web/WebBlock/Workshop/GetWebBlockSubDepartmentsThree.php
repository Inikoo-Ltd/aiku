<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentsThree;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSubDepartmentsThree
{
    use AsObject;
    use HasSubDepartmentsThree;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        return $this->getSubDepartmentsThree($webpage, $webBlock);
    }
}
