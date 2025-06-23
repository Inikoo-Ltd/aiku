<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-10h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Traits;

use TheIconic\NameParser\Parser;

trait WithProcessContactNameComponents
{
    protected function processComponents(string $contactName): array
    {
        $parser = new Parser();
        $parsedName =  $parser->parse($contactName);
        $component = [
            'first_name' => $parsedName->getFirstName(),
            'middle_name' => $parsedName->getMiddleName(),
            'last_name'  => $parsedName->getLastName(),
            'initials' => $parsedName->getInitials(),
            'suffix' => $parsedName->getSuffix(),
        ];

        return $component;
    }
}
