<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:52:25 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Wowsbar;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchWowsbarEmployee extends FetchWowsbar
{
    public function fetch(int $id): ?array
    {
        $this->wowModelData = $this->fetchData($id);

        if ($this->wowModelData) {
            $this->parseModel();
            $this->parseUserFromEmployee();
            // Do not parse job positions, use aiku UI instead
            // $this->parseJobPositions();
        }

        return $this->parsedData;
    }

    protected function parseModel(): void
    {
        $this->parsedData['employee'] = [
            'alias' => $this->wowModelData->alias,
            'contact_name' => $this->wowModelData->contact_name,
            'worker_number' => $this->wowModelData->worker_number,
            'employment_start_at' => $this->wowModelData->employment_start_at,
            'job_title' => $this->wowModelData->job_title,
            'type' => $this->wowModelData->type,
            'state' => $this->wowModelData->state,
            'created_at' => $this->wowModelData->created_at,
            'source_id' => $this->organisation->id.':'.$this->wowModelData->id,

        ];
    }

    private function parseJobPositions(): void
    {
        $query = DB::connection('wowsbar')
            ->table('job_positionables')
            ->leftJoin('job_positions', 'job_positionables.job_position_id', '=', 'job_positions.id')
            ->where('job_positionables.job_positionable_type', 'Employee')
            ->where('job_positionables.job_positionable_id', $this->wowModelData->id)
            ->get();

        foreach ($query as $jobPosition) {
            $this->parsedData['employee']['positions'][] = $this->parseJobPosition($jobPosition->slug);
        }
    }

    private function parseUserFromEmployee(): void
    {
        $wowsbarUserData = DB::connection('wowsbar')
            ->table('organisation_users')
            ->where('parent_type', 'Employee')
            ->where('parent_id', $this->wowModelData->id)
            ->first();

        if ($wowsbarUserData) {
            $userData = [
                'source_id' => $this->organisation->id.':'.$wowsbarUserData->id,
                'username' => $wowsbarUserData->username,
                'status' => $wowsbarUserData->status,
                'user_model_status' => $wowsbarUserData->status,
                'created_at' => $wowsbarUserData->created_at,
                'password' => (app()->isLocal() ? 'hello' : wordwrap(Str::random(), 4, '-', true)),
                'reset_password' => false,
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];

            $this->parsedData['user'] = $userData;
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('wowsbar')
            ->table('employees')
            ->where('id', $id)->first();
    }

    protected function parseJobPosition($sourceCode): string
    {
        return match ($sourceCode) {
            'dev-m' => 'saas-m',
            'dev-w' => 'saas-c',
            'social-w' => 'social-c',
            'ppc-w' => 'ppc-c',
            'seo-w' => 'seo-c',
            default => $sourceCode
        };
    }
}
