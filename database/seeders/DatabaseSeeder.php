<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role; // Import Role model
use Spatie\Permission\Models\Permission; // Import Permission model

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions (if you add specific permissions later, they would go here)
        // For now, we'll rely on roles for simplicity as per our plan.

        // Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $requesterRole = Role::firstOrCreate(['name' => 'requester']);
        $procurementRole = Role::firstOrCreate(['name' => 'procurement']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $programCoordinatorRole = Role::firstOrCreate(['name' => 'program_coordinator']);
        $chiefOfficerRole = Role::firstOrCreate(['name' => 'chief_officer']);

        // Create Users and Assign Roles
        // Admin User
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'), // Or use Hash::make('password')
                'email_verified_at' => now(),
            ]
        )->assignRole($adminRole);

        // Requester User
        User::firstOrCreate(
            ['email' => 'requester@example.com'],
            [
                'name' => 'Requester User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        )->assignRole($requesterRole);

        // Procurement User
        User::firstOrCreate(
            ['email' => 'procurement@example.com'],
            [
                'name' => 'Procurement Officer',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        )->assignRole($procurementRole);

        // Accountant User
        User::firstOrCreate(
            ['email' => 'accountant@example.com'],
            [
                'name' => 'Accountant',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        )->assignRole($accountantRole);

        // Program Coordinator User
        User::firstOrCreate(
            ['email' => 'coordinator@example.com'],
            [
                'name' => 'Program Coordinator',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        )->assignRole($programCoordinatorRole);

        // Chief Officer User
        User::firstOrCreate(
            ['email' => 'chief_officer@example.com'],
            [
                'name' => 'Chief Officer',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        )->assignRole($chiefOfficerRole);
    }
}
