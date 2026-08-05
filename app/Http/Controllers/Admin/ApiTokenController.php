<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('admin/ApiTokens', [
            'tokens' => $user->tokens()
                ->latest()
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->getKey(),
                    'name' => $token->name,
                    'createdAtDiff' => $token->created_at?->diffForHumans(),
                    'lastUsedAtDiff' => $token->last_used_at?->diffForHumans(),
                ])
                ->all(),
            // Flashed once by store(), through the redirect, then gone.
            'createdToken' => $request->session()->get('createdToken'),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $token = $user->createToken($request->string('name')->toString());

        return to_route('admin.api-tokens.index')->with('createdToken', [
            'name' => $token->accessToken->name,
            'plainText' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        // Scoped to the acting user's own tokens: another user's id deletes nothing.
        $user->tokens()->whereKey($token)->delete();

        return to_route('admin.api-tokens.index');
    }
}
