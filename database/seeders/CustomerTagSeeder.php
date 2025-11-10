<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 07 Nov 2025 16:07:09 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace Database\Seeders;

use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Models\Helpers\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CustomerTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/datasets/customer-tags/aiku-customer-tags.json');

        if (!File::exists($jsonPath)) {
            $this->command->error("File JSON tidak ditemukan: {$jsonPath}");
            return;
        }

        $tags = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Error decoding JSON: ' . json_last_error_msg());
            return;
        }

        foreach ($tags as $tag) {
            Tag::firstOrCreate(
                [
                    'name' => $tag['name'],
                    'scope' => TagScopeEnum::from($tag['scope']),
                ],
                [
                    'group_id' => $tag['group_id'],
                    'data' => $tag['data'],
                    'number_models' => 0,
                ]
            );
        }

        $this->command->info('Successfully seeded ' . count($tags) . ' system customer tags for RFM segmentation.');
    }
}
