<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private array $personalModels = ['User', 'WebUser', 'Employee', 'Guest'];

    public function up(): void
    {
        DB::table('media')
            ->whereIn('model_type', $this->personalModels)
            ->where('collection_name', 'image')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('model_has_media')
                ->whereColumn('model_has_media.media_id', 'media.id')
                ->where('model_has_media.scope', 'avatar'))
            ->update(['collection_name' => 'avatar']);
    }

    public function down(): void
    {
        DB::table('media')
            ->whereIn('model_type', $this->personalModels)
            ->where('collection_name', 'avatar')
            ->update(['collection_name' => 'image']);
    }
};
