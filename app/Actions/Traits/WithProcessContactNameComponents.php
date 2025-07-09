<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-10h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Traits;

use Sentry;
use TheIconic\NameParser\Parser;

trait WithProcessContactNameComponents
{
    protected function processContactNameComponents(?string $contactName): array
    {
        if ($contactName == null) {
            $contactName = '';
        }


        $parser = new Parser();
        try {
            $parsedName = $parser->parse($contactName);

            return [
                'first_name'  => $parsedName->getFirstName(),
                'middle_name' => $parsedName->getMiddleName(),
                'last_name'   => $parsedName->getLastName(),
                'initials'    => $parsedName->getInitials(),
                'suffix'      => $parsedName->getSuffix(),
            ];
        } catch (\Exception $e) {
            Sentry::captureMessage('Error parsing contact name components ->'.$contactName.'<-');
            Sentry::captureException($e);

            return [
                'first_name'  => $contactName,
                'middle_name' => '',
                'last_name'   => '',
                'initials'    => '',
                'suffix'      => '',
            ];
        }
    }
}
