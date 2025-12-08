<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Transfers\Aurora\FetchAuroraDeletedUsers;
use App\Actions\Transfers\Aurora\FetchAuroraUsers;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;

trait WithAuroraSysAdminParsers
{
    protected function parseUser($sourceId): ?User
    {
        $user = User::withTrashed()->where('source_id', $sourceId)->first();
        if ($user) {
            return $user;
        }

        $user = User::withTrashed()
            ->where('group_id', $this->organisation->group_id)
            ->whereJsonContains('sources->users', $sourceId)
            ->first();

        if ($user) {
            return $user;
        }

        $sourceData = explode(':', $sourceId);
        $user = FetchAuroraUsers::run($this->organisationSource, $sourceData[1]);

        if (! $user) {
            $user = FetchAuroraDeletedUsers::run($this->organisationSource, $sourceData[1]);
        }

        return $user;
    }

    protected function parseUserFromHistory(): User|WebUser|null
    {
        $user = null;

        if ($this->auroraModelData->{'Subject'} == 'Staff' and $this->auroraModelData->{'Subject Key'} > 0) {
            $employee = $this->parseEmployee(
                $this->organisation->id.':'.$this->auroraModelData->{'Subject Key'}
            );

            if ($employee) {
                $user = $employee->getUser();
                if (! $user) {
                    $userHasModel = DB::table('user_has_models')
                        ->where('model_id', $employee->id)
                        ->where('model_type', 'Employee')
                        ->first();
                    if ($userHasModel) {
                        $user = User::withTrashed()->find($userHasModel->user_id);
                    }
                }
            }

            if (! $user) {
                $guest = Guest::withTrashed()
                    ->where('group_id', $this->organisation->group_id)
                    ->where('source_id', $this->organisation->id.':'.$this->auroraModelData->{'Subject Key'})
                    ->first();
                if ($guest) {
                    $userHasModel = DB::table('user_has_models')
                        ->where('model_id', $guest->id)
                        ->where('model_type', 'Guest')
                        ->first();
                    if ($userHasModel) {
                        $user = User::withTrashed()->find($userHasModel->user_id);
                    }
                }
            }

            if (! $user) {
                $user = User::withTrashed()
                    ->where('group_id', $this->organisation->group_id)
                    ->whereJsonContains('sources->parents', $this->organisation->id.':'.$this->auroraModelData->{'Subject Key'})
                    ->first();
            }

            if (! $user) {
                $user = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'User Key'});
            }

            if (! $user) {
                dd($this->auroraModelData);
            }
        }

        if ($this->auroraModelData->{'Subject'} == 'Customer' and $this->auroraModelData->{'Subject Key'} > 0) {
            foreach (
                DB::connection('aurora')
                    ->table('Website User Dimension')
                    ->where('Website User Customer Key', $this->auroraModelData->{'Subject Key'})
                    ->select('Website User Key as source_id')
                    ->orderBy('source_id')->get() as $webUserData
            ) {
                $user = $this->parseWebUser($this->organisation->id.':'.$webUserData->source_id);
            }
        }

        if ($user) {
            return $user;
        }

        return $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'User Key'});
    }

    protected function parseUserPhoto(): array
    {
        $profileImages = $this->getModelImagesCollection(
            'Staff',
            $this->auroraModelData->{'Staff Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $profileImages->toArray();
    }
}
