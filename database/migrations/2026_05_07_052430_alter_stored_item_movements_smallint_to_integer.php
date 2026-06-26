<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE stored_item_movements DROP CONSTRAINT IF EXISTS stored_item_movements_stored_item_id_foreign');
        DB::statement('ALTER TABLE stored_item_movements DROP CONSTRAINT IF EXISTS stored_item_movements_location_id_foreign');

        DB::statement('ALTER TABLE stored_item_movements ALTER COLUMN stored_item_id TYPE INTEGER USING stored_item_id::INTEGER');
        DB::statement('ALTER TABLE stored_item_movements ALTER COLUMN location_id TYPE INTEGER USING location_id::INTEGER');

        DB::statement('ALTER TABLE stored_item_movements ADD CONSTRAINT stored_item_movements_stored_item_id_foreign FOREIGN KEY (stored_item_id) REFERENCES stored_items(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE stored_item_movements ADD CONSTRAINT stored_item_movements_location_id_foreign FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stored_item_movements DROP CONSTRAINT IF EXISTS stored_item_movements_stored_item_id_foreign');
        DB::statement('ALTER TABLE stored_item_movements DROP CONSTRAINT IF EXISTS stored_item_movements_location_id_foreign');

        DB::statement('ALTER TABLE stored_item_movements ALTER COLUMN stored_item_id TYPE SMALLINT USING stored_item_id::SMALLINT');
        DB::statement('ALTER TABLE stored_item_movements ALTER COLUMN location_id TYPE SMALLINT USING location_id::SMALLINT');

        DB::statement('ALTER TABLE stored_item_movements ADD CONSTRAINT stored_item_movements_stored_item_id_foreign FOREIGN KEY (stored_item_id) REFERENCES stored_items(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE stored_item_movements ADD CONSTRAINT stored_item_movements_location_id_foreign FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE');
    }
};
