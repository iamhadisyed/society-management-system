<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Fixed, code-defined permission catalogue. Roles (dynamic, per-society)
 * are assembled from these via checkboxes in the Society Admin panel -
 * see App\Services\RoleProvisioningService for the default role grants.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = [
            'Units' => ['units.manage', 'units.import', 'units.view'],
            'Billing' => [
                'billing.charge_heads.manage', 'billing.rate_matrix.manage', 'billing.overrides.manage',
                'billing.bill_runs.generate', 'billing.bill_runs.lock', 'billing.adjustments.manage', 'billing.view',
            ],
            'Payments' => [
                'payments.collect', 'payments.approve_proof', 'payments.reconcile', 'payments.view',
            ],
            'Verification' => ['verification.approve', 'verification.resolve_disputes', 'verification.release_unit'],
            'Complaints' => [
                'complaints.view_all', 'complaints.reassign', 'complaints.set_priority',
                'complaints.resolve', 'complaints.manage_departments',
            ],
            'SOS' => ['sos.receive', 'sos.acknowledge', 'sos.resolve', 'sos.view_log'],
            'BloodBank' => ['blood_bank.manage'],
            'HomeServices' => ['home_services.manage'],
            'CarPooling' => ['car_pooling.moderate'],
            'Forms' => ['forms.manage', 'forms.review'],
            'Visitors' => ['visitors.manage', 'visitors.gate_operate', 'vehicles.manage', 'domestic_staff.manage'],
            'Community' => [
                'notices.manage', 'events.manage', 'gallery.manage', 'info_desk.manage',
                'polls.manage', 'lost_found.manage', 'marketplace.moderate', 'directory.view',
                'facilities.manage', 'facility_bookings.approve', 'water_tanker.manage',
            ],
            'Ads' => ['ads.manage'],
            'Expenses' => ['expenses.manage', 'vendors.manage', 'reports.financial.view'],
            'Staff' => ['staff.manage', 'staff.attendance.mark', 'payroll.manage'],
            'RBAC' => ['roles.manage', 'staff_accounts.manage'],
            'Society' => ['society.settings.manage', 'audit_log.view'],
            'OwnershipTransfers' => ['ownership_transfers.manage', 'ownership_transfers.view'],
            'Elections' => ['elections.manage'],
        ];

        foreach ($catalogue as $group => $keys) {
            foreach ($keys as $key) {
                Permission::updateOrCreate(
                    ['key' => $key],
                    ['group' => $group, 'label' => ucwords(str_replace(['_', '.'], ' ', $key))]
                );
            }
        }
    }
}
