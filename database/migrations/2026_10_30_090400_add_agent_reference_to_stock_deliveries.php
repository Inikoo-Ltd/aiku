<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-30 09:04
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Tabs of AW Indo's moving stock spreadsheets (UK_Moving_Stock, Slovakia Moving Stock, Spain Moving Stock New),
     * matched to the containers by the product codes they list.
     */
    private const array AW_INDO_STUFFINGS = [
        'aw' => [
            'AWIndo-084 (40HC) NSUB07260052'          => 'Stuffing 25 July 2026',
            'AWIndo-083 (40HC) TCLU8866411'           => 'Stuffing 20 April 2026',
            'AWIndo-082 (40HC) MSKU0289100'           => 'Stuffing 27 February 2026',
            'AWIndo-081 Con2 (40HC) MRSU6507750.'     => 'Stuffing 09 February 2026',
            'AWIndo-081 Con1 (40HC) MIEU2002156'      => 'Stuffing 14 January 2026',
            'AWIndo-080 (40HC) UETU5601201'           => 'Stuffing 17 November 2025',
            'AWIndo-079_UK (40HC) MSDU7865655'        => 'Stuffing 21 September 2025',
            'AWIndo-078_UK (40HC) MRSU5315049'        => 'Stuffing 07 Agustus 2025',
            'AWIndo-077_UK (40HC) MOTU1411269'        => 'Stuffing 30 June 2025',
            'AWIndo-076_Cont 2_UK (40HC) UETU6319002' => 'Stuffing 22 April 2025',
            'AWIndo-076_Cont 1_UK (40HC) MSMU7466656' => 'Stuffing 21 April 2025',
            'AWIndo-075_UK (40HC) UETU7939055'        => 'Stuffing 17 Feb 2025',
            'AWIndo-074 (40HC) FFAU2037207'           => 'Stuffing 20 Januari 2025',
        ],
        'sk' => [
            'AWIndo-43-VAL260294'     => 'STUFFING 27 JUNE 2026',
            'AWIndo-42-LagermaxAW01'  => 'STUFFING 28 FEBRUARY 2026',
            'AWIndo-41-VAL260123'     => 'STUFFING 09 JANUARY 2026',
            'AWIndo-40-VAL260038'     => 'STUFFING 07 NOVEMBER 2025',
            'AWIndo-39-Lagermax-AW07' => 'STUFFING 29 SEPTEMBER 2025',
            'AWIndo-38-VAL250372'     => 'STUFFING 01 AUGUST 2025',
            'AWIndo-37-Lagermax'      => 'STUFFING 19 MEI 2025 (JOGJA)',
            'AWIndo-36-VAL250180'     => 'STUFFING 26 FEB 2025',
            'AWIndo-35-VAL250147'     => 'STUFFING 27 JAN 2025',
            'AWIndo-34-VAL250090'     => 'STUFFING 15 JAN 2025',
        ],
        'es' => [
            'AWIndo-20'             => 'STUFFING 06 MAY 2026',
            'AWIndo-19New'          => 'STUFFING 16 DECEMBER 2025',
            'AWIndo-18 (with Gas)'  => 'STUFFING 11 SEPT 2025 JOGJA',
            'AWIndo-17'             => 'STUFFING 17 MEI 2025',
        ],
    ];

    public function up(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->string('agent_reference')->nullable()->index()->comment('what the agent calls this shipment, e.g. its spreadsheet tab "Stuffing 25 July 2026"; reference stays the buyer\'s');
        });

        foreach (self::AW_INDO_STUFFINGS as $organisationSlug => $stuffings) {
            $organisationId = DB::table('organisations')->where('slug', $organisationSlug)->value('id');
            if (!$organisationId) {
                continue;
            }

            foreach ($stuffings as $reference => $agentReference) {
                DB::table('stock_deliveries')
                    ->where('organisation_id', $organisationId)
                    ->where('reference', $reference)
                    ->where('state', 'placed')
                    ->whereNull('deleted_at')
                    ->update(['agent_reference' => $agentReference]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('stock_deliveries', function (Blueprint $table) {
            $table->dropColumn('agent_reference');
        });
    }
};
