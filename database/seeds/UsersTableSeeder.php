<?php

use Illuminate\Database\Seeder;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = array(
            'admin' => 1,
            'name' => 'Admin',
            'email' => 'admin@shopx.com',
            'password' => bcrypt('password')
        );

        User::create($user);

        $fake = Faker\Factory::create();

        for($u = 0; $u < 10; $u++) {
            $user = array(
                'name' => $fake->name,
                'email' => $fake->safeEmail,
                'password' => bcrypt('password')
            );

            $user = User::create($user);

            // add address 


            // add orders 



        }
    }
}
