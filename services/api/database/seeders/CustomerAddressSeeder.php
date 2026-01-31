<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    public function run(): void
    {
        $cities = ['Sofia', 'Plovdiv', 'Varna', 'Burgas', 'Ruse', 'Stara Zagora', 'Pleven', 'Blagoevgrad'];
        $streets = [
            'Vitosha Blvd',
            'Tsar Simeon St.',
            'Ivan Vazov St.',
            'Hristo Botev Blvd',
            'Patriarch Evtimiy Blvd',
            'Slivnitsa Blvd',
            'Shipka St.',
            'Rakovski St.',
            'Bulgaria Blvd',
            'Aleksandar Stamboliyski Blvd',
        ];

        $customers = Customer::query()->orderBy('id')->get();

        foreach ($customers as $index => $customer) {
            $city = $cities[$index % count($cities)];
            $street = $streets[$index % count($streets)];

            CustomerAddress::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                ],
                [
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                    'country' => 'Bulgaria',
                    'city' => $city,
                    'postal_code' => str_pad((string) (1000 + ($index % 9000)), 4, '0', STR_PAD_LEFT),
                    'address_line1' => $street.' #'.(10 + $index),
                    'address_line2' => null,
                    'is_default' => true,
                ],
            );
        }
    }
}
