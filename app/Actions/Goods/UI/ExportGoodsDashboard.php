<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The full Goods dashboard catalogue, filtered exactly like the page, streamed as a CSV so it never
 * loads the whole export into memory at once.
 */
class ExportGoodsDashboard extends OrgAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [
            'organisation' => ['sometimes', 'nullable', 'string'],
            'family'       => ['sometimes', 'nullable', 'string'],
            'condition'    => ['sometimes', 'nullable', Rule::in(ShowGoodsDashboard::CONDITIONS)],
            'state'        => ['sometimes', 'nullable', Rule::in(OrgStockStateEnum::values())],
            'search'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort'         => ['sometimes', 'nullable', Rule::in(array_merge(ShowGoodsDashboard::SORTS, array_map(fn ($sort) => '-'.$sort, ShowGoodsDashboard::SORTS)))],
            'period'       => ['sometimes', 'nullable', Rule::in(ShowGoodsDashboard::PERIODS)],
        ];
    }

    public function asController(ActionRequest $request): StreamedResponse
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->validatedData);
    }

    public function handle(array $filters): StreamedResponse
    {
        $data          = ShowGoodsDashboard::make()->forGroup($this->group)->exportData($filters);
        $organisations = $data['organisations'];

        $headings = array_merge(
            ['Code', 'Name', 'Family', 'Sales', 'Trend %', 'Cover weeks', 'Group status'],
            collect($organisations)->flatMap(fn (array $organisation) => [
                $organisation['code'].' available',
                $organisation['code'].' inbound',
                $organisation['code'].' next expected',
                $organisation['code'].' condition',
                $organisation['code'].' status',
            ])->all()
        );

        $filename = now()->format('Y-m-d').'-goods-dashboard-'.rand(111, 999).'.csv';

        return response()->streamDownload(function () use ($data, $organisations, $headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings, ',', '"', '');

            foreach ($data['rows'] as $row) {
                $line = [
                    $row['code'],
                    $row['name'],
                    $row['family_code'],
                    $row['sales'],
                    $row['trend'],
                    $row['cover_weeks'],
                    OrgStockStateEnum::from($row['state'])->name,
                ];

                foreach ($organisations as $organisation) {
                    $cell = $row['organisations'][$organisation['code']] ?? null;
                    array_push(
                        $line,
                        $cell['available'] ?? null,
                        $cell['inbound'] ?? null,
                        $cell['next_expected_at'] ?? null,
                        $cell['condition'] ?? null,
                        $cell['state'] ?? null,
                    );
                }

                fputcsv($handle, $line, ',', '"', '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
