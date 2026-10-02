<?php

namespace App\Actions\Devotionals;

use App\Models\Devotional;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads today's devotional proof (Blueprint §9, Contractor flow §V). Submitting again the
 * same day replaces the file. For compliance only — never a payroll deduction.
 */
class SubmitDevotional
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(User $employee, string $title, UploadedFile $file, ?CarbonImmutable $now = null): Devotional
    {
        $now ??= CarbonImmutable::now();

        $existing = $employee->devotionals()->whereDate('date', $now->startOfDay())->first();
        $previousPath = $existing?->file_path;
        $path = $file->store("devotionals/{$employee->id}", 'local');

        $devotional = DB::transaction(function () use ($employee, $existing, $title, $file, $path, $now) {
            $attributes = [
                'title' => $title,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'submitted_at' => $now,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return $employee->devotionals()->create([...$attributes, 'date' => $now->startOfDay()]);
        });

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        $this->activity->log('devotionals', $existing ? 'Replaced devotional' : 'Submitted devotional', $devotional, $employee->name);

        return $devotional;
    }
}
