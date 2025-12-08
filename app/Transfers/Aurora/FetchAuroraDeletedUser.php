<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Sept 2024 10:42:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class FetchAuroraDeletedUser extends FetchAurora
{
    protected function parseModel(): void
    {

        if (! $this->auroraModelData->{'User Deleted Metadata'}) {
            $auroraDeletedData = new stdClass;
        } else {
            $auroraDeletedData = json_decode(gzuncompress($this->auroraModelData->{'User Deleted Metadata'}));
            $auroraDeletedData = $auroraDeletedData->data;
        }

        $parent = null;
        if ($auroraDeletedData->{'User Type'} == 'Staff') {
            $parent = $this->parseEmployee($this->organisation->id.':'.$auroraDeletedData->{'User Parent Key'});
        }
        $parentSource = $this->organisation->id.':'.$auroraDeletedData->{'User Parent Key'};

        $data = [
            'deleted' => ['source' => 'aurora'],
        ];

        $relatedUsername = $this->auroraModelData->{'User Deleted Handle'};
        if ($this->auroraModelData->aiku_alt_username) {
            $relatedUsername = $this->auroraModelData->aiku_alt_username;
        }
        $this->parsedData['related_username'] = Str::kebab(Str::lower($relatedUsername));

        $username = $auroraDeletedData->{'User Handle'}.'-deleted_aurora_'.$this->organisation->id.'_'.$this->auroraModelData->{'User Deleted Key'};

        $this->parsedData['parent'] = $parent;
        $this->parsedData['parentSource'] = $parentSource;

        $this->parsedData['add_guest'] = $this->auroraModelData->aiku_add_guest == 'Yes';

        $this->parsedData['user'] =
            [
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'User Deleted Key'},
                'username' => Str::kebab(Str::lower($username)),
                'status' => false,
                'pivot_status' => false,
                'created_at' => $auroraDeletedData->{'User Created'},
                'legacy_password' => Str::random(60),
                'language_id' => $this->parseLanguageID($auroraDeletedData->{'User Preferred Locale'}),
                'reset_password' => false,
                'data' => $data,
                'deleted_at' => $this->auroraModelData->{'User Deleted Date'},
                'fetched_at' => now(),
                'last_fetched_at' => now(),
                'password' => Str::random(60),
            ];

        if ($this->parsedData['add_guest']) {
            $this->parsedData['guest'] = $this->getGuestData($auroraDeletedData);
        }
    }

    protected function getGuestData($auroraDeletedData): array
    {

        return [
            'code' => $auroraDeletedData->{'User Handle'},
            'contact_name' => $auroraDeletedData->{'User Alias'},
            'phone' => $auroraDeletedData->{'User Password Recovery Mobile'},
            'email' => $auroraDeletedData->{'User Password Recovery Email'},
            'source_id' => $this->organisation->id.':'.$auroraDeletedData->{'User Parent Key'},
            'status' => false,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'user' => $this->parsedData['user'],
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('User Deleted Dimension')
            ->where('User Deleted Key', $id)->first();
    }
}
