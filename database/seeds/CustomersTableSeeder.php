<?php

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CustomerAddress;

class CustomersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $fake = Faker\Factory::create();

        for($u = 0; $u < 10; $u++) {
            $user_data = array(
                'name' => $fake->name,
                'email' => $fake->safeEmail,
                'password' => bcrypt('password')
            );

            $user = User::create($user_data);

            $make_default = true;
            for($a = 0; $a < rand(1,3); $a++) {
                $address_data = array(
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone' => $fake->phoneNumber,
                    'address_line_1' => $fake->streetName,
                    'city' => $fake->city,
                    'postcode' => $fake->postcode,
                    'country_code' => 'GB',
                    'default' => $make_default ? 1 : 0
                );
                CustomerAddress::create($address_data);
                $make_default = false;
            }
        }
    }
}
