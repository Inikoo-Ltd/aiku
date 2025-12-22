<?php

/*
 * author Louis Perez
 * created on 22-12-2025-10h-48m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Helpers\Organisation\UI;

use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrganisationOptions
{
    use AsObject;

    public function handle(): array
    {

        $selectOptions = [];
        /** @var Organisation $org */
        foreach (Organisation::where('type', 'shop')->get() as $org) {

            $selectOptions[$org->id] =
                [
                    'label'   => $org->code.' | '.$org->name,
                ];
        }

        return $selectOptions;

    }
}
