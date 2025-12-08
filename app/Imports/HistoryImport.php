<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 08 Jun 2023 14:34:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Imports;

use App\Actions\Elasticsearch\StoreElasticsearchDocument;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class HistoryImport implements ToCollection
{
    public function collection(Collection $collection): void
    {
        $i = 0;

        foreach ($collection as $row) {
            if ($i > 0) {
                StoreElasticsearchDocument::dispatch($row[0], $row[2], $row[1]);
            }

            $i++;
        }
    }
}
