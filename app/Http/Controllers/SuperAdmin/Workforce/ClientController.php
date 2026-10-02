<?php

namespace App\Http\Controllers\SuperAdmin\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workforce Management → Clients (Blueprint §5), view-only. Admins add clients when they give them
 * to contractors; the Super Admin approves each assignment in Requests & Approvals.
 */
class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $clients = Client::query()
            ->withCount(['rates as assigned_count' => fn (Builder $query) => $query->current()])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->where('client_name', 'like', "%{$search}%")->orWhere('client_code', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('client_name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->client_name,
                'code' => $client->client_code,
                'status' => $client->is_active ? 'active' : 'inactive',
                'assignedCount' => $client->assigned_count,
                'createdAt' => $client->created_at?->toDateString(),
            ]);

        return Inertia::render('SuperAdmin/Workforce/Clients', [
            'clients' => Paginated::from($clients),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'counts' => [
                'total' => Client::query()->count(),
                'active' => Client::query()->active()->count(),
                'inactive' => Client::query()->where('is_active', false)->count(),
            ],
        ]);
    }
}
