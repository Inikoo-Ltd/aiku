<?php

namespace Database\Factories;

use App\Models\Dropshipping\MagentoUser;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MagentoUser>
 */
class MagentoUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // Buat CustomerSalesChannel baru jika belum ada
        $customerSalesChannel = Customer::factory()->create();

        return [
            'group_id' => 1, // Ganti dengan ID grup yang valid
            'organisation_id' => 1, // Ganti dengan ID organisasi yang valid
            'customer_id' => $customerSalesChannel->customer_id, // Ambil dari customer sales channel
            'status' => true,
            'name' => $this->faker->name,
            'username' => $this->faker->userName,
            'password' => $this->faker->password,
            'customer_sales_channel_id' => $customerSalesChannel->id,
            'state' => 'active', // Pastikan nilai ini sesuai dengan enum Anda
            'auth_type' => 'oauth', // Pastikan nilai ini sesuai dengan enum Anda
            'settings' => [
                'access_token' => 'your_magento_api_token_here',
                'base_url' => 'https://your-magento-site.com/'
            ]
        ];
    }
}
