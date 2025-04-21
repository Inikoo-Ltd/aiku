<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Apr 2025 11:08:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\FetchStack;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Transfers\FetchStack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreFetchStacks
{
    use AsAction;
    use WithOrganisationSource;


    /**
     * @throws \Exception
     */
    public function handle(?Command $command): void
    {
        /** @var Organisation $organisation */
        foreach (Organisation::where('type', OrganisationTypeEnum::SHOP)->get() as $organisation) {
            $command->line('Processing '.$organisation->name);
            $this->setSource($organisation);
            $data = [];


            $results = DB::connection('aurora')->table('Stack Aiku Dimension')->orderBy('Stack Aiku Creation Date')->get();

            foreach ($results as $row) {
                $operation   = $row->{'Stack Aiku Operation'};
                $operationId = $row->{'Stack Aiku Operation Key'};

                $data[$row->{'Stack Aiku Key'}] = [
                    'group_id'        => $organisation->group_id,
                    'organisation_id' => $organisation->id,
                    'operation'       => $operation,
                    'operation_id'    => $operationId,
                    'submitted_at'    => $row->{'Stack Aiku Creation Date'},
                ];
            }

            foreach ($data as $key => $row) {
                FetchStack::create($row);
                DB::connection('aurora')->table('Stack Aiku Dimension')->where('Stack Aiku Key', $key)->delete();
            }

            $command->info("Processed: ".count($data));
        }
    }


    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);
    }

    public function getCommandSignature(): string
    {
        return 'fetch_stacks:store';
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        $this->handle($command);

        return 0;
    }
}
