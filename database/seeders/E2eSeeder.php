<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'email'       => 'e2e@princess.test',
            'name'        => 'E2E User',
            'external_id' => 'e2e-user',
            'username'    => 'e2e',
        ]);
    }
}
