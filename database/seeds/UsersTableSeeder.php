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
        $users[] = array(
            'admin' => 1,
            'name' => 'Hasan Tareque',
            'email' => 'admin@uxblondon.com',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 1,
        );

        $users[] = array(
            'admin' => 1,
            'name' => 'Paul Nanneley',
            'email' => 'paul@uxblondon.com',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 1,
        );

        $users[] = array(
            'admin' => 1,
            'name' => 'Sean O\'Halloran',
            'email' => 'sean@uxblondon.com',
            'password' => bcrypt('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 1,
        );


        DB::table('users')->insert($users);
        
    }
}
