<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Exports\Portfolio;

use Maatwebsite\Excel\Concerns\FromArray;

class DropshippingClientTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ["contact_name", "company_name", "email", "phone", "address_line_1", "address_line_2", "postal_code", "locality", "country_code"]
        ];
    }
}
