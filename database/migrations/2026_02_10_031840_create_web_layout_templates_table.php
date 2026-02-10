<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('web_layout_templates', function (Blueprint $table) {
            $table->id();
            
            $table->string('label')->index();
            $table->string('type');
            $table->string('scope');
            $table->jsonb('data');
            
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['type', 'scope']);
            $table->index('scope');
        });

        DB::statement('
                CREATE INDEX web_layout_templates_data_gin 
                ON web_layout_templates 
                USING GIN (data)
        ');
    }


    public function down(): void
    {
         DB::statement('
            DROP INDEX IF EXISTS web_layout_templates_data_gin
        ');

        Schema::dropIfExists('web_layout_templates');
    }
};
