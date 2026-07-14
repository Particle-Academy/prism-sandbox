<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * GitHub OAuth login. Links a member to their GitHub identity so contribution
 * webhooks can attribute XP by `github_username`.
 */
class GitHubLoginController
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $github = Socialite::driver('github')->user();

        $user = User::firstOrNew(['github_id' => (string) $github->getId()]);

        $user->github_username = $github->getNickname();
        $user->avatar_url = $github->getAvatar();
        $user->name = $github->getName() ?: (string) $github->getNickname();

        if (! $user->exists) {
            $user->email = $github->getEmail() ?: $github->getNickname().'@users.noreply.github.com';
            $user->password = bcrypt(Str::random(40)); // OAuth-only account; never used to log in
        }

        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        return redirect('/');
    }
}
