<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Oct 2024 17:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;

trait WithParseUpdateHistory
{
    protected function parseHistoryUpdatedOldValues($auditable): array
    {
        $oldValues = [];

        $field = $this->getField();

        $haystack = $this->auroraModelData->{'History Details'};
        $haystack = trim(preg_replace('/\s+/', ' ', $haystack));

        if ($haystack == '') {
            $haystack = $this->auroraModelData->{'History Abstract'};
            $haystack = trim(preg_replace('/\s+/', ' ', $haystack));
        }

        if (preg_match('/<div class="field tr"><div>Old value:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $oldValues = $this->extractFromTable($matches, $oldValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Alter Wert:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $oldValues = $this->extractFromTable($matches, $oldValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Původní hodnota:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $oldValues = $this->extractFromTable($matches, $oldValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Stará hodnota:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $oldValues = $this->extractFromTable($matches, $oldValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Előző érték:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $oldValues = $this->extractFromTable($matches, $oldValues, $field, $auditable);
        } elseif (preg_match('/changed from Price: £([\d.]+)/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/changed from Price: €([\d.]+)/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/changed from Price: zł([\d.]+)/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/&rArr; názov spoločnosti(.+) bol odstránen/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/Action:<\/div><div>Associated<\/div>/', $haystack, $matches)) {
            $oldValues[$field] = '';
        } elseif (preg_match("/Prospect's Prospect Preferred Contact Number Formatted Number was deleted/", $haystack, $matches)) {
            $oldValues[$field] = 'Unknown number';
        } elseif (preg_match("/Prospect's Prospect Preferred Contact Number Formatted Number byl smazán/", $haystack, $matches)) {
            $oldValues[$field] = 'Unknown number';
        } elseif (preg_match('/company name (.*) was deleted$/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/contact name (.*) was deleted$/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        } elseif (preg_match('/contact name was deleted$/', $haystack, $matches)) {
            $oldValues[$field] = 'Unknown name';
        } elseif (preg_match('/price changed from ([£€])([\d.]+)/', $haystack, $matches)) {
            $oldValues[$field] = $matches[2];
        } elseif (preg_match('/price changed from zł([\d.]+)/', $haystack, $matches)) {
            $oldValues[$field] = $matches[1];
        }

        return $oldValues;
    }

    protected function parseHistoryUpdatedNewValues($auditable): array
    {
        $newValues = [];

        $field = $this->getField();

        $haystack = $this->auroraModelData->{'History Details'};

        $haystack = trim(preg_replace('/\s+/', ' ', $haystack));

        if (preg_match('/<div class="field tr"><div>New value:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Neuer Wert:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Nová hodnota:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Nová hodnota:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Új érték:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Valor nuevo:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Novo valor\s*:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Nouvelle valeur\s*:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Nowa wartość\s*:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/<div class="field tr"><div>Nuovo valore\s*:<\/div><div>(.*)<\/div><\/div>/', $haystack, $matches)) {
            $newValues = $this->extractFromTable($matches, $newValues, $field, $auditable);
        } elseif (preg_match('/to Price: ([£€])([\d.]+)/', $haystack, $matches)) {
            $newValues[$field] = $matches[2];
        } elseif (preg_match('/to Price: zł([\d.]+)/', $haystack, $matches)) {
            $newValues[$field] = $matches[1];
        } elseif (preg_match('/to zł([\d.]+)/', $haystack, $matches)) {
            $newValues[$field] = $matches[1];
        }

        if (count($newValues) == 0) {
            $haystack2 = $this->auroraModelData->{'History Abstract'};

            $haystack2 = trim(preg_replace('/\s+/', ' ', $haystack2));

            if (preg_match('/^Product Name Changed \((.+)\)/', $haystack2, $matches)) {
                $newValues[$field] = $matches[1];
            }
        }

        return $newValues;
    }

    protected function postProcessValues(string $field, string $value, $auditable): array
    {
        return match ($field) {
            'price' => $this->postProcessPrice($value),
            'state' => $this->postProcessState($value, $auditable),
            default => [$value, []]
        };
    }

    protected function postProcessPrice(string $value): array
    {
        $extraValues = [];

        if (preg_match('/([\d.]+).+margin\s+([\d.]+)%/', $value, $matches)) {
            $value = $matches[1];
            $extraValues['margin'] = $matches[2];
        }

        return [$value, $extraValues];
    }

    protected function postProcessState(string $value, $auditable): array
    {
        $extraValues = [];
        $value = trim($value);

        $debug = $value;

        if ($auditable instanceof Product) {
            $value = match ($value) {
                'InProcess' => ProductStateEnum::IN_PROCESS,
                'Active', 'Suspended', 'Aktiv', 'Aktív', 'Aktívny', 'Suspendované', 'Felfüggesztett' => ProductStateEnum::ACTIVE,
                'Discontinuing' => ProductStateEnum::DISCONTINUING,
                'Discontinued', 'aus dem Sortiment genommen', 'Vyradený' => ProductStateEnum::DISCONTINUED,
                default => null
            };
        }

        if (! $value) {
            print_r($this->auroraModelData);
            dd($debug);
        }

        return [$value, $extraValues];
    }

    protected function extractFromTable($matches, $values, $field, $auditable): array
    {
        $matches[1] = preg_replace('/<\/div.*$/', '', $matches[1]);
        $value = trim($matches[1]);
        [$value, $extraValues] = $this->postProcessValues($field, $value, $auditable);
        $values[$field] = $value;

        return array_merge($values, $extraValues);
    }
}
