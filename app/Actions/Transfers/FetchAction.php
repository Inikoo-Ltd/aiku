<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Apr 2024 15:24:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers;

use App\Actions\Helpers\Fetch\StoreFetch;
use App\Actions\Helpers\Fetch\UpdateFetch;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Transfers\Fetch\FetchTypeEnum;
use App\Enums\Transfers\FetchRecord\FetchRecordTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use App\Transfers\SourceOrganisationService;
use App\Transfers\WowsbarOrganisationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class FetchAction implements ShouldBeUnique
{
    use AsAction;
    use WithOrganisationSource;
    use WithSaveMigrationHistory;

    protected int $counter = 0;
    protected ?ProgressBar $progressBar;

    protected bool $allowLegacy = false;


    protected int $number_stores = 0;
    protected int $number_updates = 0;
    protected int $number_no_changes = 0;
    protected int $number_errors = 0;

    protected AuroraOrganisationService|WowsbarOrganisationService|SourceOrganisationService|null $organisationSource = null;

    protected int $hydratorsDelay = 5;

    protected ?Shop $shop;
    protected array $with;
    protected bool $onlyNew = false;
    protected bool $basket = false;
    protected bool $fetchAll = false;
    protected string $dbSuffix = '';
    protected ?array $model = null;

    protected bool $onlyOrdersNoTransactions = false;
    protected ?int $fromDays = null;
    protected bool $orderDesc = false;
    protected bool $onlyRefunds = false;


    public function __construct()
    {

        $this->progressBar = null;
        $this->shop        = null;
        $this->with        = [];
    }

    protected function getFetchType(Command $command): FetchTypeEnum
    {
        return FetchTypeEnum::BASE;
    }

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): Model|array|null
    {
        return null;
    }

    public function getModelsQuery(): ?Builder
    {
        return null;
    }

    public function fetchAll(SourceOrganisationService $organisationSource, Command $command = null): void
    {
        $this->getModelsQuery()->chunk(10000, function ($chunkedData) use ($command, $organisationSource) {
            foreach ($chunkedData as $auroraData) {
                if ($command && $command->getOutput()->isDebug()) {
                    $command->line("Fetching: ".$auroraData->{'source_id'});
                }
                $model = $this->handle($organisationSource, $auroraData->{'source_id'});
                unset($model);
                $this->progressBar?->advance();
            }
        });
    }

    public function count(): ?int
    {
        return null;
    }

    public function reset(): void
    {
    }

    protected function getOrganisations(Command $command): Collection
    {
        $query = Organisation::orderBy('id');
        if ($command->argument('organisations')) {
            $query->whereIn('slug', $command->argument('organisations'));
        }

        return $query->get();
    }


    protected function preProcessCommand(Command $command)
    {
    }

    protected function doReset(Command $command)
    {
    }

    public function asCommand(Command $command): int
    {
        $this->hydratorsDelay = 120;


        $organisations = $this->getOrganisations($command);
        $exitCode      = 0;

        foreach ($organisations as $organisation) {
            $this->preProcessCommand($command);

            $result = $this->processOrganisation($command, $organisation);

            if ($result !== 0) {
                $exitCode = $result;
            }
        }

        return $exitCode;
    }


    public function recordError($organisationSource, $e, $modelData, $modelType = null, $errorOn = null): void
    {

        \Sentry\captureException($e);

        if(!$organisationSource->fetch){
            return;
        }

        $this->number_errors++;


        UpdateFetch::run($organisationSource->fetch, ['number_errors' => $this->number_errors]);
        $organisationSource->fetch->records()->create([
            'model_data' => $modelData,
            'data'       => $e->getMessage(),
            'type'       => FetchRecordTypeEnum::ERROR,
            'source_id'  => Arr::get($modelData, 'source_id'),
            'model_type' => $modelType,
            'error_on'   => $errorOn
        ]);
    }

    public function recordFetchError($organisationSource, $modelData, $modelType = null, $errorOn = null, $data = []): void
    {
        if (!$organisationSource->fetch) {
            return;
        }

        $this->number_errors++;
        UpdateFetch::run($organisationSource->fetch, ['number_errors' => $this->number_errors]);
        $organisationSource->fetch->records()->create([
            'model_data' => $modelData,
            'data'       => $data,
            'type'       => FetchRecordTypeEnum::FETCH_ERROR,
            'source_id'  => Arr::get($modelData, 'source_id'),
            'model_type' => $modelType,
            'error_on'   => $errorOn
        ]);
    }

    public function recordChange($organisationSource, $wasChanged): void
    {
        if (!$organisationSource->fetch) {
            return;
        }

        if ($wasChanged) {
            $this->number_updates++;
            UpdateFetch::run($organisationSource->fetch, ['number_updates' => $this->number_updates]);
        } else {
            $this->number_no_changes++;
            UpdateFetch::run($organisationSource->fetch, ['number_no_changes' => $this->number_no_changes]);
        }
    }

    public function recordNew($organisationSource): void
    {
        if (isset($organisationSource->fetch)) {
            $this->number_stores++;
            UpdateFetch::run($organisationSource->fetch, ['number_stores' => $this->number_stores]);
        }
    }

    public function getDBPrefix(Command $command): string
    {
        return '';
    }

    public function processOrganisation(Command $command, Organisation $organisation): int
    {
        try {
            $this->organisationSource = $this->getOrganisationSource($organisation);
        } catch (Exception $exception) {
            $command->error($exception->getMessage());

            return 1;
        }

        $this->organisationSource->initialisation($organisation, $this->getDBPrefix($command));

        $this->organisationSource->fetch = StoreFetch::run(
            [
                'type' => $this->getFetchType($command),
                'data' => [
                    'command'   => $command->getName(),
                    'arguments' => $command->arguments(),
                    'options'   => $command->options(),
                ]
            ]
        );


        $command->info('');

        if ($command->option('source_id')) {
            $this->handle($this->organisationSource, $command->option('source_id'));

            if ($this->organisationSource->fetch) {
                UpdateFetch::run($this->organisationSource->fetch, ['number_items' => 1]);
            }
        } else {
            $numberItems = $this->count() ?? 0;

            if ($this->organisationSource->fetch) {
                UpdateFetch::run($this->organisationSource->fetch, ['number_items' => $numberItems]);
            }

            if (!$command->option('quiet') and !$command->getOutput()->isDebug()) {
                $info = '✊ '.$command->getName().' '.$organisation->slug;
                if ($this->shop) {
                    $info .= ' shop:'.$this->shop->slug;
                }
                $command->line($info);
                $this->progressBar = $command->getOutput()->createProgressBar($this->count() ?? 0);
                $this->progressBar->setFormat('debug');
                $this->progressBar->start();
            } else {
                $command->line('Steps '.number_format($this->count()));
            }

            $this->fetchAll($this->organisationSource, $command);
            $this->progressBar?->finish();
        }
        if ($this->organisationSource->fetch) {
            UpdateFetch::run($this->organisationSource->fetch, ['finished_at' => now()]);
        }

        return 0;
    }


}
