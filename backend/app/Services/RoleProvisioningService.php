<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Society;

/**
 * Creates the seeded default roles (spec: "Seed these default roles per
 * society") when a new society is onboarded. Society Administrators can
 * freely edit permissions on these afterwards (is_default just marks
 * them as pre-seeded, it doesn't lock them) and create further roles.
 */
class RoleProvisioningService
{
    /** @return array<string, array<string>> role slug => label, permission keys */
    public static function defaults(): array
    {
        return [
            'society-administrator' => ['Society Administrator', ['*']],
            'billing-officer' => ['Billing Officer', [
                'billing.charge_heads.manage', 'billing.rate_matrix.manage', 'billing.overrides.manage',
                'billing.bill_runs.generate', 'billing.bill_runs.lock', 'billing.adjustments.manage', 'billing.view', 'units.view',
            ]],
            'collection-officer' => ['Collection Officer (Cashier)', [
                'payments.collect', 'payments.approve_proof', 'payments.reconcile', 'payments.view', 'billing.view', 'units.view',
            ]],
            'accounts-officer' => ['Accounts Officer', [
                'expenses.manage', 'vendors.manage', 'reports.financial.view', 'billing.view', 'payments.view',
            ]],
            'hr-staff-supervisor' => ['HR & Staff Supervisor', [
                'staff.manage', 'staff.attendance.mark', 'payroll.manage',
            ]],
            'department-head' => ['Department Head', [
                'complaints.view_all', 'complaints.reassign', 'complaints.set_priority', 'complaints.resolve',
            ]],
            'department-agent' => ['Department Agent (Field Agent)', [
                'complaints.resolve',
            ]],
            'security-supervisor' => ['Security Supervisor', [
                'sos.receive', 'sos.acknowledge', 'sos.resolve', 'sos.view_log', 'visitors.manage', 'visitors.gate_operate', 'vehicles.manage', 'domestic_staff.manage',
            ]],
            'gate-guard' => ['Gate Guard', [
                'sos.receive', 'sos.acknowledge', 'visitors.gate_operate', 'vehicles.manage', 'domestic_staff.manage',
            ]],
            'communications-officer' => ['Communications Officer', [
                'notices.manage', 'events.manage', 'gallery.manage', 'info_desk.manage', 'polls.manage', 'ads.manage',
            ]],
        ];
    }

    public function provision(Society $society): void
    {
        $allPermissionIds = Permission::pluck('id', 'key');

        foreach (self::defaults() as $slug => [$label, $permissionKeys]) {
            $role = Role::withoutGlobalScopes()->firstOrCreate(
                ['society_id' => $society->id, 'slug' => $slug],
                ['name' => $label, 'is_default' => true]
            );

            $ids = in_array('*', $permissionKeys, true)
                ? $allPermissionIds->values()->all()
                : collect($permissionKeys)->map(fn ($k) => $allPermissionIds[$k] ?? null)->filter()->values()->all();

            $role->permissions()->sync($ids);
        }
    }
}
