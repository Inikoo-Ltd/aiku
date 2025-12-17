<?php

namespace App\Actions\Reports;

use App\Actions\OrgAction;
use App\Actions\Accounting\Intrastat\UI\IndexIntrastatReport;
use App\Actions\Accounting\SageInvoices\UI\IndexSageInvoicesReport;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\OverviewResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetReports extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(Organisation $organisation): AnonymousResourceCollection
    {
        $sections = $this->getSection($organisation);

        $dataRaw = collect($sections)->map(function ($data, $section) {
            return (object)[
                'section' => $section,
                'data'    => collect($data)->map(function ($item) {
                    return (object)$item;
                }),
            ];
        });

        return OverviewResource::collection($dataRaw);
    }

    public function getSection(Organisation $organisation): array
    {
        return [
            __('Reports') => $this->getReportsSections($organisation),
        ];
    }

    protected function getReportsSections(Organisation $organisation): array
    {
        return [
            [
                'name'  => __('Intrastat'),
                'icon'  => 'fal fa-file-export',
                'route' => route('grp.org.reports.intrastat', $organisation),
                'count' => IndexIntrastatReport::make()->inReports($organisation),
            ],
            [
                'name'  => __('Sage Invoices'),
                'icon'  => 'fal fa-file-invoice',
                'route' => route('grp.org.reports.sage-invoices', $organisation),
                'count' => IndexSageInvoicesReport::make()->inReports($organisation),
            ],
        ];
    }
}
