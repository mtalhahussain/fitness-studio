<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GymDomainResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private GymDomainResolver $domainResolver) {}

    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'showDemo' => $this->demoAccessGranted($request),
            'hostGym'  => $this->domainResolver->resolveByHost($request->getHost()),
        ]);
    }

    /**
     * Demo credentials are only revealed to visitors who arrive via
     * /login?demo=<DEMO_ACCESS_KEY>. The grant is kept in session so it
     * survives the redirect back after a failed sign-in attempt.
     */
    private function demoAccessGranted(Request $request): bool
    {
        $key = (string) config('app.demo_access_key');

        if ($key === '') {
            return false;
        }

        $given = $request->query('demo');

        if (is_string($given) && hash_equals($key, $given)) {
            $request->session()->put('demo_access', true);
        }

        return (bool) $request->session()->get('demo_access', false);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user->status !== 'active') {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account has been suspended.']);
            }

            $hostGym = $this->domainResolver->resolveByHost($request->getHost());

            if ($hostGym && ! $user->isAdmin()) {
                if ((int) $user->gym_id !== (int) $hostGym->id) {
                    Auth::logout();
                    return back()->withErrors(['email' => 'This account is not linked to this gym domain.']);
                }

                if (! $hostGym->isActive()) {
                    Auth::logout();
                    return back()->withErrors(['email' => 'This gym domain is inactive. Please contact support.']);
                }
            }

            if ($hostGym && $user->isAdmin()) {
                session(['admin_active_gym_id' => $hostGym->id]);
            }

            $user->update(['last_login_at' => now()]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
