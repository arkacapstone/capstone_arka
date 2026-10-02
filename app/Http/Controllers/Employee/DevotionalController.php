<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Devotionals\SubmitDevotional;
use App\Http\Controllers\Controller;
use App\Models\Devotional;
use App\Support\Paginated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contractor → Devotional (Contractor flow §V). The contractor's own record — no deductions attached.
 */
class DevotionalController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $today = CarbonImmutable::today();

        $records = $user->devotionals()
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Devotional $devotional) => $this->present($devotional));

        $todays = $user->devotionals()->whereDate('date', $today)->first();

        return Inertia::render('Employee/Devotionals', [
            'today' => $today->toDateString(),
            'submission' => $todays ? $this->present($todays) : null,
            'thisMonth' => $user->devotionals()->whereDate('date', '>=', $today->startOfMonth())->whereDate('date', '<=', $today)->count(),
            'daysSoFar' => $today->day,
            'records' => Paginated::from($records),
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
        ]);
    }

    public function store(Request $request, SubmitDevotional $submit): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,docx,jpg,jpeg,png', 'max:10240'],
        ], [
            'file.uploaded' => 'This file could not be uploaded. Please use a file under 10 MB.',
            'file.max' => 'This file is larger than 10 MB. Please use a smaller file.',
        ]);

        $submit->handle($request->user(), $validated['title'], $validated['file']);

        return back()->with('success', "Devotional submitted. Thank you — that's today done.");
    }

    public function file(Request $request, Devotional $devotional): StreamedResponse
    {
        abort_unless($devotional->employee_id === $request->user()->id, 404);
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
            'fileUrl' => route('employee.devotionals.file', $devotional),
        ];
    }
}
