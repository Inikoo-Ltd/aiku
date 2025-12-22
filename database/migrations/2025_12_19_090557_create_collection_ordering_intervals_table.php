<?php

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::create('collection_ordering_intervals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections');
            $table = $this->unsignedIntegerDateIntervals($table, [
                'invoices',
                'refunds',
                'orders',
                'delivery_notes',
                'customers_invoiced'
            ]);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('collection_ordering_intervals');
    }
};
