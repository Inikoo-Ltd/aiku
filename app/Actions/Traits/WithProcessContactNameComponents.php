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
        // Normalize and sanitize input to avoid parser TypeError on invalid UTF-8
        $contactName = $contactName ?? '';
        if (!is_string($contactName)) {
            $contactName = (string)$contactName;
        }
        $contactName = trim($contactName);

        // Remove control characters (except whitespace like tab/newline) and ensure valid UTF-8
        $contactName = preg_replace('/[^\P{C}\t\n\r]+/u', '', $contactName) ?? '';
        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $contactName);
        if ($sanitized !== false) {
            $contactName = $sanitized;
        }



        if ($contactName === '') {
            return [
                'first_name'  => '',
                'middle_name' => '',
                'last_name'   => '',
                'initials'    => '',
                'suffix'      => '',
            ];
        }

        $parser = new Parser();
        try {
            $parsedName = $parser->parse($contactName);

            try {
                $firstName = $parsedName->getFirstName();
            } catch (\Throwable $e) {
                Sentry::captureMessage('Name parser failed to getFirstName for: ' . $contactName);
                Sentry::captureException($e);
                $firstName = $contactName;
            }


            $encodedValues=json_encode(
                [
                    'first_name'  => $firstName ?? '',
                    'middle_name' => $parsedName->getMiddleName(),
                    'last_name'   => $parsedName->getLastName(),
                    'initials'    => $parsedName->getInitials(),
                    'suffix'      => $parsedName->getSuffix(),
                ]
            );

            if(!$encodedValues){
                $encodedValues=json_encode(
                    [
                        'first_name'  => $contactName,
                        'middle_name' => '',
                        'last_name'   => '',
                        'initials'    => '',
                        'suffix'      =>'',
                    ]
                );
                if($encodedValues){
                    return json_decode($encodedValues, true);
                }else{
                    return [
                        'first_name'  => '',
                        'middle_name' => '',
                        'last_name'   => '',
                        'initials'    => '',
                        'suffix'      => '',
                    ];
                }
            }



            return [
                'first_name'  => $firstName ?? '',
                'middle_name' => $parsedName->getMiddleName(),
                'last_name'   => $parsedName->getLastName(),
                'initials'    => $parsedName->getInitials(),
                'suffix'      => $parsedName->getSuffix(),
            ];
        } catch (\Throwable $e) {
            Sentry::captureMessage('Error parsing contact name components ->' . $contactName . '<-');
            Sentry::captureException($e);

            // Fallback: naive split into first and last
            $parts = preg_split('/\s+/u', $contactName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $first = $parts[0] ?? $contactName;
            $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

            return [
                'first_name'  => $first,
                'middle_name' => '',
                'last_name'   => $last,
                'initials'    => '',
                'suffix'      => '',
            ];
        }
    }
}
