<?php

use App\Actions\Transfers\FetchAction;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Regression for the --only_new OFFSET bug: handle() sets aiku_id on the source row,
 * so a whereNull('aiku_id') query shrinks while chunk() pages by OFFSET and roughly
 * half the backlog gets skipped. fetchAll() must visit every row exactly once.
 * Runs on an in-memory sqlite standing in for the aurora connection; no shared DB.
 */
function keysetFetcher(int $pageSize, bool $orderDesc = false): FetchAction
{
    $fetcher = new class () extends FetchAction {
        public array $fetched = [];

        public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): null
        {
            $this->fetched[] = $organisationSourceId;
            DB::connection('aurora_keyset_test')
                ->table('Thing Dimension')
                ->where('Thing Key', $organisationSourceId)
                ->update(['aiku_id' => $organisationSourceId]);

            return null;
        }

        public function getModelsQuery(): Builder
        {
            return DB::connection('aurora_keyset_test')
                ->table('Thing Dimension')
                ->select('Thing Key as source_id')
                ->whereNull('aiku_id')
                ->orderBy('Thing Date');
        }
    };

    (function () use ($pageSize, $orderDesc) {
        $this->fetchPageSize = $pageSize;
        $this->orderDesc     = $orderDesc;
    })->call($fetcher);

    return $fetcher;
}

beforeEach(function () {
    config(['database.connections.aurora_keyset_test' => [
        'driver'   => 'sqlite',
        'database' => ':memory:',
    ]]);
    DB::connection('aurora_keyset_test')->statement(
        'create table "Thing Dimension" ("Thing Key" integer primary key, "Thing Date" text, "aiku_id" integer)'
    );
    DB::connection('aurora_keyset_test')->table('Thing Dimension')->insert(
        collect(range(1, 25))->map(fn ($i) => [
            'Thing Key'  => $i,
            'Thing Date' => sprintf('2026-01-%02d', 26 - $i),
            'aiku_id'    => null,
        ])->all()
    );

    $this->organisationSource = Mockery::mock(SourceOrganisationService::class);
});

afterEach(function () {
    DB::purge('aurora_keyset_test');
});

it('fetches every row exactly once even though handle() removes rows from the filtered set', function () {
    $fetcher = keysetFetcher(10);
    $fetcher->fetchAll($this->organisationSource);

    expect($fetcher->fetched)->toBe(range(1, 25));
});

it('walks the keyset backwards when orderDesc is set', function () {
    $fetcher = keysetFetcher(10, orderDesc: true);
    $fetcher->fetchAll($this->organisationSource);

    expect($fetcher->fetched)->toBe(range(25, 1));
});

it('honours a query-level limit smaller than the page size', function () {
    $fetcher = new class () extends FetchAction {
        public array $fetched = [];

        public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): null
        {
            $this->fetched[] = $organisationSourceId;

            return null;
        }

        public function getModelsQuery(): Builder
        {
            return DB::connection('aurora_keyset_test')
                ->table('Thing Dimension')
                ->select('Thing Key as source_id')
                ->limit(5);
        }
    };

    $fetcher->fetchAll($this->organisationSource);

    expect($fetcher->fetched)->toHaveCount(5);
});
