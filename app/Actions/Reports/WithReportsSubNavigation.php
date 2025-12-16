<?php

namespace App\Actions\Reports;

use App\Models\SysAdmin\Organisation;

trait WithReportsSubNavigation
{
    protected function getReportsNavigation(Organisation $organisation): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __('Reports'),
                'route'    => [
                    'name'       => 'grp.org.reports.index',
                    'parameters' => [$organisation->slug],
                ],
            ],
            [
                'label' => __('Intrastat'),
                'route' => [
                    'name'       => 'grp.org.reports.intrastat',
                    'parameters' => [$organisation->slug],
                ],
            ],
            [
                'label' => __('Sage Invoices'),
                'route' => [
                    'name'       => 'grp.org.reports.sage-invoices',
                    'parameters' => [$organisation->slug],
                ],
            ],
        ];
    }
}
