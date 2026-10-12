<?php

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use App\Models\Society;
use App\Models\User;
use App\Services\RoleProvisioningService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        PlatformAdmin::firstOrCreate(
            ['email' => 'platform@admin.test'],
            ['name' => 'Platform Administrator', 'password' => 'password']
        );

        $society = Society::firstOrCreate(
            ['code' => 'DEMO01'],
            [
                'name' => 'Demo Housing Society',
                'city' => 'Lahore',
                'bank_name' => 'Demo Bank',
                'bank_account_number' => '0000000000',
                'bank_iban' => 'PK00DEMO0000000000000000',
                'status' => 'active',
            ]
        );

        (new RoleProvisioningService)->provision($society);

        $admin = User::withoutGlobalScopes()->firstOrCreate(
            ['society_id' => $society->id, 'email' => 'admin@demo.test'],
            ['user_type' => 'society_staff', 'name' => 'Demo Admin', 'password' => 'password']
        );

        $adminRole = $society->roles()->withoutGlobalScopes()->where('slug', 'society-administrator')->first();
        if ($adminRole && ! $admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole->id);
        }

        // Realistic Pakistani sample data (blocks, marla categories, tariff types, charge heads, sample units)
        $this->call(SocietySampleDataSeeder::class);
    }
}
