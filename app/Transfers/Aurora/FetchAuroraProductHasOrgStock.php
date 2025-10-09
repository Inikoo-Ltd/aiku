<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraProductHasOrgStock extends FetchAurora
{
    protected function parseModel(): void
    {
        $productStocks = [];
        foreach ($this->auroraModelData as $modelData) {
            $orgStock = $this->parseOrgStock($this->organisation->id.':'.$modelData->{'Product Part Part SKU'});

            if ($orgStock) {
                $ratio = $modelData->{'Product Part Ratio'};


                if ($ratio == 0.08400) {
                    $ratio = 1 / 12;
                }

                if ($ratio == 0.06300) {
                    $ratio = 1 / 16;
                }

                if ($ratio == 0.09260 || $ratio == 0.09257 || $ratio == 0.09250 || $ratio == 0.09259) {
                    $ratio = 1 / 108;
                }


                if ($ratio == 0.23900) {
                    $ratio = 1 / 42;
                }

                if ($ratio == 0.16800) {
                    $ratio = 1 / 6;
                }

                if ($ratio == 0.02800 || $ratio == 0.27800 || $ratio == 0.27700) {
                    $ratio = 1 / 36;
                }


                if ($ratio == 0.35700) {
                    $ratio = 1 / 28;
                }

                if ($ratio == 0.04100) {
                    $ratio = 1 / 24;
                }

                if ($ratio == 0.07100) {
                    $ratio = 1 / 14;
                }

                if ($ratio == 0.18700) {
                    $ratio = 3 / 16;
                }

                if ($ratio == 0.05600 || $ratio == 0.55500 || $ratio == 0.05500) {
                    $ratio = 1 / 18;
                }


                list($smallestDividend, $correspondingDivisor) = findSmallestFactors($ratio);


                $correctedRatio = $smallestDividend / $correspondingDivisor;

                //                $diff = abs($correctedRatio - $ratio);
                //
                //                if ($diff != 0 and $diff>0.01) {
                //         print "$ratio $smallestDividend  $correspondingDivisor  $correctedRatio   $diff  $orgStock->slug $orgStock->source_id   \n";
                //                }


                $ratio = $smallestDividend / $correspondingDivisor;

                //                dd(
                //                    [
                //                        'quantity'        => $ratio,
                //                        'notes'           => $modelData->{'Product Part Note'} ?? null,
                //                        'source_id'       => $this->organisation->id.':'.$modelData->{'Product Part Key'},
                //                        'dividend'        => $smallestDividend,
                //                        'divisor'         => $correspondingDivisor,
                //                        'last_fetched_at' => now(),
                //                    ]
                //                );


                $productStocks[$orgStock->id] = [
                    'quantity'        => $ratio,
                    'notes'           => $modelData->{'Product Part Note'} ?? null,
                    'source_id'       => $this->organisation->id.':'.$modelData->{'Product Part Key'},
                    'dividend'        => $smallestDividend,
                    'divisor'         => $correspondingDivisor,
                    'last_fetched_at' => now(),
                ];
            }
        }
        $this->parsedData['org_stocks'] = $productStocks;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Product Part Bridge')
            ->where('Product Part Product ID', $id)->get();
    }
}
