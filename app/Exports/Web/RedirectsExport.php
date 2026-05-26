<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-05-25 15:13
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Exports\Web;

use App\Models\Web\Redirect;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RedirectsExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(protected Website $website)
    {
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        return Redirect::query()
            ->where('redirects.website_id', $this->website->id)
            ->leftJoin('webpages', 'redirects.to_webpage_id', '=', 'webpages.id')
            ->select([
                'redirects.id',
                'redirects.type',
                'redirects.from_url',
                'redirects.from_path',
                'webpages.code as to_webpage_code',
                'webpages.canonical_url as to_webpage_url',
                'redirects.created_at',
            ]);
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->type instanceof \BackedEnum ? $row->type->value : $row->type,
            $row->from_url,
            $row->from_path,
            $row->to_webpage_code,
            $row->to_webpage_url,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Type',
            'From URL',
            'From Path',
            'To Webpage Code',
            'To Webpage URL',
            'Created At',
        ];
    }
}
