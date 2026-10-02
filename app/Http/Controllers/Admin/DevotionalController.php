<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Devotional;
use App\Models\User;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Devotional Management (Blueprint §9, Admin flow §VI). Monitoring only: Admins view
 * what was submitted and never edit it, and compliance never creates a payroll deduction.
 */
class DevotionalController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:submitted,not_submitted'],
        ]);

        $date = isset($filters['date']) ? CarbonImmutable::parse($filters['date']) : CarbonImmutable::today();
        $onDate = fn ($query) => $query->whereDate('date', $date);

        $employees = User::query()
            ->workforce()
            ->active()
            ->with(['devotionals' => $onDate])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")
            ))
            ->when(($filters['status'] ?? null) === 'submitted', fn (Builder $query) => $query->whereHas('devotionals', $onDate))
            ->when(($filters['status'] ?? null) === 'not_submitted', fn (Builder $query) => $query->whereDoesntHave('devotionals', $onDate))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $employee) {
                $devotional = $employee->devotionals->first();

                return [
                    'employee' => ['id' => $employee->id, 'name' => $employee->name, 'code' => $employee->employee_code],
                    'devotional' => $devotional ? $this->present($devotional) : null,
                ];
            });

        $expected = User::query()->workforce()->active()->count();
        $submitted = User::query()->workforce()->active()->whereHas('devotionals', $onDate)->count();

        return Inertia::render('Admin/Devotionals/Index', [
            'rows' => Paginated::from($employees),
            'filters' => [
                'date' => $date->toDateString(),
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'summary' => ['expected' => $expected, 'submitted' => $submitted],
        ]);
    }

    public function history(User $account): Response
    {
        abort_unless($account->hasEmployeePortal(), 404);

        $devotionals = $account->devotionals()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Devotional $devotional) => $this->present($devotional));

        return Inertia::render('Admin/Devotionals/History', [
            'employee' => ['id' => $account->id, 'name' => $account->name, 'code' => $account->employee_code],
            'devotionals' => Paginated::from($devotionals),
            'thisMonth' => $account->devotionals()->whereDate('date', '>=', CarbonImmutable::today()->startOfMonth())->count(),
            'daysSoFar' => CarbonImmutable::today()->day,
        ]);
    }

    /**
     * Read-only view of the uploaded proof.
     */
    public function file(Devotional $devotional): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($devotional->file_path), 404);

        return Storage::disk('local')->response($devotional->file_path, $devotional->file_name, [], 'inline');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Devotional $devotional): array
    {
        return [
            'id' => $devotional->id,
            'date' => $devotional->date->toDateString(),
            'title' => $devotional->title,
            'fileName' => $devotional->file_name,
            'fileSize' => $devotional->file_size,
            'submittedAt' => $devotional->submitted_at->toIso8601String(),
            'late' => $devotional->isLate(),
            'fileUrl' => route('admin.devotionals.file', $devotional),
        ];
    }
}
