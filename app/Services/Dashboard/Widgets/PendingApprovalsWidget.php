<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\PayrollStatus;
use App\Models\CashAdvance;
use App\Models\ClientAssignmentRequest;
use App\Models\DeviceDeduction;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Services\Dashboard\Contracts\DashboardWidget;

/**
 * Decisions that only the Super Admin can make (Blueprint §2, §15).
 */
class PendingApprovalsWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'pendingApprovals';
    }

    /**
     * @return array{total: int, items: list<array{key: string, label: string, count: int, href: string}>}
     */
    public function data(): array
    {
        $items = [
            $this->item('leave', 'Leave requests', LeaveRequest::query()->pending()->count(), 'super-admin.requests'),
            $this->item('cash_advances', 'Cash advances', CashAdvance::query()->pending()->count(), 'super-admin.cash-advances'),
            $this->item('payroll', 'Payroll awaiting approval', Payroll::query()->where('status', PayrollStatus::Reviewed)->count(), 'super-admin.payroll'),
            $this->item('device_deductions', 'Device deductions', DeviceDeduction::query()->pending()->count(), 'super-admin.requests'),
            $this->item('client_assignments', 'Client assignments', ClientAssignmentRequest::query()->pending()->count(), 'super-admin.requests', ['type' => 'clients']),
            $this->item('overtime', 'Overtime tickets', OvertimeRequest::query()->pending()->count(), 'super-admin.requests', ['type' => 'overtime']),
        ];

        return [
            'total' => array_sum(array_column($items, 'count')),
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{key: string, label: string, count: int, href: string}
     */
    private function item(string $key, string $label, int $count, string $routeName, array $parameters = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'href' => route($routeName, $parameters),
        ];
    }
}
