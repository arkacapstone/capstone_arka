<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\HomeOffice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Community Chat: one room per home office, for every role.
 */
class CommunityChatTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $contractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->admin = User::factory()->admin()->create();
        $this->contractor = User::factory()->create();
    }

    public function test_every_role_can_open_the_chat_and_post(): void
    {
        foreach ([$this->superAdmin, $this->admin, $this->contractor] as $user) {
            $this->actingAs($user)
                ->post(route('community.messages.store'), ['body' => "Hello from {$user->name}"])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->contractor)
            ->get(route('community.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Community/Index')
                ->has('messages', 3)
                ->where('messages.0.sender.name', $this->superAdmin->name)
                ->where('messages.0.mine', false)
                ->where('messages.2.mine', true)
                ->where('memberCount', 3));

        // One community room for the whole home office.
        $this->assertSame(1, Conversation::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseCount('conversation_participants', 3);
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->actingAs($this->contractor)
            ->post(route('community.messages.store'), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_opening_the_chat_marks_it_read(): void
    {
        $this->actingAs($this->contractor)->get(route('community.index'));

        $this->assertNotNull(
            Conversation::query()->withoutGlobalScopes()->sole()->participants()->whereKey($this->contractor->id)->sole()->pivot->last_read_at
        );
    }

    public function test_people_delete_their_own_messages_and_the_super_admin_any(): void
    {
        $this->actingAs($this->admin)->post(route('community.messages.store'), ['body' => 'From the admin']);
        $message = Message::query()->withoutGlobalScopes()->sole();

        $this->actingAs($this->contractor)
            ->delete(route('community.messages.destroy', $message))
            ->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->delete(route('community.messages.destroy', $message))
            ->assertRedirect();

        $this->assertDatabaseCount('messages', 0);

        $this->actingAs($this->contractor)->post(route('community.messages.store'), ['body' => 'Mine']);
        $own = Message::query()->withoutGlobalScopes()->sole();

        $this->actingAs($this->contractor)
            ->delete(route('community.messages.destroy', $own))
            ->assertRedirect();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_each_home_office_has_its_own_chat(): void
    {
        $this->actingAs($this->contractor)->post(route('community.messages.store'), ['body' => 'Makarius only']);
        $message = Message::query()->withoutGlobalScopes()->sole();

        $other = HomeOffice::query()->create(['name' => 'Harbor Staffing']);
        $outsider = User::factory()->superAdmin()->create(['home_office_id' => $other->id]);

        $this->actingAs($outsider)
            ->get(route('community.index'))
            ->assertInertia(fn (Assert $page) => $page->has('messages', 0)->where('memberCount', 1));

        // Even by its link, another home office's message is not found.
        $this->actingAs($outsider)
            ->delete(route('community.messages.destroy', $message))
            ->assertNotFound();

        $this->assertSame(2, Conversation::query()->withoutGlobalScopes()->count());
    }

    public function test_the_sidebar_links_to_the_chat_for_every_role(): void
    {
        foreach ([$this->superAdmin, $this->admin, $this->contractor] as $user) {
            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($items) => collect($items)->contains('key', 'community')));
        }
    }
}
