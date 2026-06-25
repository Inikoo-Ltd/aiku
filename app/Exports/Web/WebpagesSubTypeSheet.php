<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Web;

use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class WebpagesSubTypeSheet implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(protected Website $website, protected ?string $subType, protected string $title)
    {
    }

    public function query(): Relation|Builder|QueryBuilder
    {
        return Webpage::query()
            ->where('webpages.website_id', $this->website->id)
            ->when($this->subType, fn ($query) => $query->where('webpages.sub_type', $this->subType))
            ->orderBy('webpages.code')
            ->select([
                'webpages.code',
                'webpages.url',
                'webpages.canonical_url',
                'webpages.seo_title',
                'webpages.seo_description',
                'webpages.index_page',
                'webpages.follow_link',
            ]);
    }

    public function map($row): array
    {
        return [
            $row->code,
            $row->canonical_url ?: $row->url,
            $row->seo_title,
            $row->seo_description,
            $row->index_page ? 'yes' : 'no',
            $row->follow_link ? 'yes' : 'no',
        ];
    }

    public function headings(): array
    {
        return [
            'Code',
            'URL',
            'SEO Title',
            'SEO Description',
            'Index',
            'Follow',
        ];
    }

    public function title(): string
    {
        return substr(str_replace(['\\', '/', '?', '*', ':', '[', ']'], ' ', $this->title), 0, 31);
    }
}
