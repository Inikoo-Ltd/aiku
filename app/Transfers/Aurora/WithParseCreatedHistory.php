<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Oct 2024 17:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

trait WithParseCreatedHistory
{
    protected function parseHistoryCreatedNewValues($auditable): array
    {
        return match (class_basename($auditable)) {
            'Customer' => $this->parseCustomerHistoryCreatedNewValues(),
            'Location' => $this->parseLocationHistoryCreatedNewValues(),
            'Product' => $this->parseProductHistoryCreatedNewValues(),
            'WarehouseArea' => $this->parseWarehouseAreaHistoryCreatedNewValues(),
            'Prospect' => $this->parseProspectHistoryCreatedNewValues(),
            default => []
        };
    }

    protected function parseHistoryCreatedData($auditable): array
    {
        return match (class_basename($auditable)) {
            'Product' => $this->parseProductHistoryCreatedData(),
            default => []
        };
    }

    protected function parseCustomerHistoryCreatedNewValues(): array
    {
        $newValues = [];
        $abstract = $this->auroraModelData->{'History Abstract'};

        if ($abstract == 'Customer Created') {
            $abstract = trim($this->auroraModelData->{'History Details'});
        }

        if ($abstract == 'Utworzono klienta') {
            $abstract = $this->auroraModelData->{'History Details'};
        }

        if ($abstract == 'Compte Client Créé') {
            $abstract = $this->auroraModelData->{'History Details'};
        }

        if ($abstract == 'Customer  registered'
            || $abstract == 'New customer  added'
            || $abstract == 'Nouveau Client  ajoute'
            || $abstract == 'New customer'
        ) {
            $newValues['name'] = '';
        }

        if (preg_match('/(.+) customer record created$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Customer (.+) registered$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Kunde (.+) erstellt$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^New customer (.+) added$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^New customer (.+) dodano$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Byl vytvořen zákaznický záznam\s?(.+)$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Bol vytvorený zákaznícky záznam\s?(.+)$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Nouveau Client (.+) soi ajoute$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Nouveau Client (.+) ajoute$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^New customer (.+)$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        }

        if (count($newValues) == 0) {
            echo ">> exit A\n";
            print_r($this->auroraModelData);
        }

        return $newValues;
    }

    protected function parseLocationHistoryCreatedNewValues(): array
    {
        $newValues = [];
        $abstract = $this->auroraModelData->{'History Abstract'};

        if ($abstract == 'Location Created') {
            $abstract = trim($this->auroraModelData->{'History Details'});
        }

        if (preg_match('/Location (.+) create/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/(.+) location record created$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/^New location (.+) added$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/^New location (.+) dodano$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/^New location (.+)$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/^(.+) location created$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        } elseif (preg_match('/^Poloha(.+) bola vytvorená$/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        }

        if (count($newValues) == 0) {
            echo ">> exit B\n";
            print_r($this->auroraModelData);
        }

        return $newValues;
    }

    protected function parseProductHistoryCreatedNewValues(): array
    {
        $newValues = [];
        $abstract = trim($this->auroraModelData->{'History Abstract'});

        if ($abstract == 'Product Created' || $abstract == 'product created' || $abstract == 'Produkt bol vytvorený') {
            $abstract = trim($this->auroraModelData->{'History Details'});
        }

        if ($abstract == '') {
            return $newValues;
        }
        $value = '';
        if (preg_match('/Product (.+) (\(\d+\)) created/', $abstract, $matches)) {
            $value = trim($matches[1]);
        } elseif (preg_match('/Produkt(.+) byl vytvořen/', $abstract, $matches)) {
            $value = trim($matches[1]);
        } elseif (preg_match('/Produkt(.+) bol vytvorený/', $abstract, $matches)) {
            $value = trim($matches[1]);
        } elseif (preg_match('/(.+) product created/', $abstract, $matches)) {
            $value = trim($matches[1]);
        } elseif (preg_match('/(.+) termék létrehozva/', $abstract, $matches)) {
            $value = trim($matches[1]);
        }

        if ($value) {
            $field = 'code';
            if (str_word_count($value) > 1) {
                $field = 'name';
            }
            $newValues[$field] = $value;
        }

        if (count($newValues) == 0) {
            echo ">> exit C\n";
            print_r($this->auroraModelData);
        }

        return $newValues;
    }

    protected function parseProductHistoryCreatedData(): array
    {
        $data = [];
        $haystack = trim($this->auroraModelData->{'History Abstract'});
        if (preg_match('/change_view\(\'upload\/(\d+)/', $haystack, $matches)) {
            $uploadSourceId = $matches[1];
            $upload = $this->parseUpload($this->organisation->id.':'.$uploadSourceId);
            $data['upload_id'] = $upload->id;
        }

        return $data;
    }

    protected function parseWarehouseAreaHistoryCreatedNewValues(): array
    {
        $newValues = [];
        $abstract = $this->auroraModelData->{'History Abstract'};

        if (preg_match('/Warehouse area <span class="italic">([a-zA-Z0-9_\s]+)/', $abstract, $matches)) {
            $newValues['code'] = trim($matches[1]);
        }

        if (count($newValues) == 0) {
            echo ">> exit D\n";
            print_r($this->auroraModelData);
        }

        return $newValues;
    }

    protected function parseProspectHistoryCreatedNewValues(): array
    {
        $newValues = [];
        $abstract = $this->auroraModelData->{'History Abstract'};

        if (preg_match('/^ prospect record created/', $abstract) ||
            $abstract == 'Byl vytvořenrospektový záznam' || $abstract == 'Bol vytvorený záznam o perspektíve'
        ) {
            return $newValues;
        }

        if (preg_match('/(.+) prospect record created/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/^Bol vytvorený záznam o perspektíve(.+)/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        } elseif (preg_match('/(.+) kilátásrekord létrehozva$/', $abstract, $matches)) {
            $newValues['name'] = trim($matches[1]);
        }

        if (count($newValues) == 0) {
            echo ">> exit E\n";
            print_r($this->auroraModelData);
        }

        return $newValues;
    }
}
