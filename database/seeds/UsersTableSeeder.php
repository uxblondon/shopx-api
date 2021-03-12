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
        
    }
}
