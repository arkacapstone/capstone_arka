<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Client;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_clients_are_listed_with_assigned_employee_counts(): void
    {
        $client = Client::factory()->create();
        Rate::factory()->count(2)->for($client)->create();
        Rate::factory()->for($client)->create(['end_date' => now()->subDay()]);
        Client::factory()->inactive()->create();

        $this->actingAs($this->superAdmin)
            ->get(route('super-admin.workforce.clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Workforce/Clients')
                ->has('clients.data', 2)
                ->where('counts', ['total' => 2, 'active' => 1, 'inactive' => 1])
                ->where('clients.data', fn ($clients) => collect($clients)->firstWhere('id', $client->id)['assignedCount'] === 2)
            );
    }

    public function test_the_super_admin_cannot_add_or_change_clients(): void
    {
        foreach (['super-admin.workforce.clients.store', 'super-admin.workforce.clients.update', 'super-admin.workforce.clients.status'] as $route) {
            $this->assertFalse(Route::has($route), "{$route} should not exist: only Admins add clients.");
        }

        $this->actingAs($this->superAdmin)
            ->post('/super-admin/workforce/clients', ['client_name' => 'Acme', 'client_code' => 'ACME'])
            ->assertStatus(405);

        $this->assertDatabaseCount(Client::class, 0);
    }
}
