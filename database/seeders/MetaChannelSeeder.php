<?php

namespace Database\Seeders;

use App\Models\Chat\MetaChannel;
use Illuminate\Database\Seeder;

class MetaChannelSeeder extends Seeder
{
    public function run(): void
    {
        MetaChannel::firstOrCreate(
            ['code' => 'whatsapp'],
            ['name' => 'WhatsApp']
        );
    }
}
