<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed one account per role. Safe to run repeatedly.
     */
    public function run(): void
    {
        $accounts = [
            ['ARKA-0001', 'Super Admin', 'superadmin@arka.co', UserRole::SuperAdmin],
            ['ARKA-0002', 'Admin', 'admin@arka.co', UserRole::Admin],
            ['ARKA-0003', 'Employee', 'employee@arka.co', UserRole::Employee],
        ];

        foreach ($accounts as [$code, $name, $email, $role]) {
            User::query()->firstOrCreate(['email' => $email], [
                'employee_code' => $code,
                'name' => $name,
                'role' => $role,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
