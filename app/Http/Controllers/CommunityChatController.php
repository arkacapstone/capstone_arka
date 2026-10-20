<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Community chat: one chat room per home office for everyone in it (Super Admin, Admins,
 * Contractors). The page refreshes its messages every few seconds (polling), so no
 * websocket server is needed.
 */
class CommunityChatController extends Controller
{
    /**
     * How many of the latest messages the page shows.
     */
    private const SHOWN = 200;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $community = Conversation::communityFor($user);
        $community->join($user);
        $community->markReadBy($user);

        $messages = $community->messages()
            ->with('sender:id,name,role')
            ->latest('id')
            ->limit(self::SHOWN)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'at' => $message->created_at->toIso8601String(),
                'mine' => $message->user_id === $user->id,
                'canDelete' => $this->canDelete($user, $message),
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'role' => $message->sender->role->label(),
                ],
            ]);

        return Inertia::render('Community/Index', [
            'messages' => $messages,
            'memberCount' => User::query()->active()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [], ['body' => 'message']);

        $user = $request->user();
        $community = Conversation::communityFor($user);
        $community->join($user);

        $community->messages()->create(['user_id' => $user->id, 'body' => trim($validated['body'])]);
        $community->markReadBy($user);

        return back();
    }

    /**
     * Removes a message: the sender's own, or any message for the Super Admin (moderation).
     * A message of another home office is not found (global scope).
     */
    public function destroy(Request $request, Message $message): RedirectResponse
    {
        abort_unless($this->canDelete($request->user(), $message), 403);

        $message->delete();

        return back();
    }

    private function canDelete(User $user, Message $message): bool
    {
        return $message->user_id === $user->id || $user->isSuperAdmin();
    }
}
