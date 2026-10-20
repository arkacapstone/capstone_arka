<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One account per role for the arka_system database (the normalized schema): a home office,
 * then its Super Admin, Admin and Contractor, each with a profile, and a
 * contractors row (Admin and Contractor). Safe to run repeatedly.
 *
 *   php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('home_offices')->updateOrInsert(['name' => 'Makarius Virtual Solutions'], ['created_at' => $now, 'updated_at' => $now]);
        $homeOfficeId = DB::table('home_offices')->where('name', 'Makarius Virtual Solutions')->value('id');

        $accounts = [
            ['ARKA-0001', 'Super Admin', 'superadmin@arka.co', 'primary_admin'],
            ['ARKA-0002', 'Admin', 'admin@arka.co', 'company_admin'],
            ['ARKA-0003', 'Contractor', 'contractor@arka.co', 'contractor'],
        ];

        foreach ($accounts as [$code, $name, $email, $role]) {
            DB::table('users')->updateOrInsert(['email' => $email], [
                'home_office_id' => $homeOfficeId,
                'employee_code' => $code,
                'name' => $name,
                'role' => $role,
                'status' => 'active',
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $userId = DB::table('users')->where('email', $email)->value('id');

            // Marked complete so the first login does not stop at "Complete your profile".
            DB::table('user_profiles')->updateOrInsert(['user_id' => $userId], [
                'profile_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Admins work shifts too (time tracker, payslips), so they get a contractors row like
            // Contractors. Its id is the user's id (shared primary key).
            if ($role !== 'primary_admin') {
                DB::table('contractors')->updateOrInsert(['user_id' => $userId], ['id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }
}
