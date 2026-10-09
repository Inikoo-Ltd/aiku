<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Exports\Web\SeoTableExport;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The SEO portfolio of every live website as an Excel file.
 */
class ExportSeoPortfolio extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function handle(): BinaryFileResponse
    {
        $portfolio = GetSeoPortfolio::run();

        return Excel::download(new SeoTableExport(
            ['Website', 'Shop', 'Site health', 'Previous site health', 'Visitors (28 days)', 'Previous visitors', 'Google clicks (28 days)', 'Previous Google clicks', 'Position', 'Tracked keywords', 'Checked keywords', 'In the top 10', 'Referring domains', 'New referring domains', 'Lost referring domains', 'Rank'],
            collect($portfolio['websites'])->map(fn (array $row) => [
                $row['domain'],
                $row['shop'],
                $row['health'],
                $row['previous_health'],
                $row['visitors'],
                $row['previous_visitors'],
                $row['clicks'],
                $row['previous_clicks'],
                $row['position'],
                $row['tracked_keywords'],
                $row['checked_keywords'],
                $row['top_10'],
                $row['referring_domains'],
                $row['new_referring'],
                $row['lost_referring'],
                $row['rank'],
            ])->all()
        ), now()->format('Y-m-d').'-seo-portfolio.xlsx');
    }

    public function asController(ActionRequest $request): BinaryFileResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }
}
