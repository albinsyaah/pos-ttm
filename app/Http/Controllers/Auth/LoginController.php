<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withErrors(['username' => 'Those credentials don\'t match our records.'])
                ->onlyInput('username');
        }

        $user = Auth::user();

        if ($user->isDisabled()) {
            Auth::logout();

            return back()
                ->withErrors(['username' => 'Your account has been disabled. Contact your administrator.'])
                ->onlyInput('username');
        }

        // Send the user to the first page their role actually grants
        // access to, rather than always the dashboard: a role like
        // "Cashier" may not have dashboard.view at all, and would 403 the
        // moment it landed there.
        $landingRoute = AccessControl::firstAccessibleRoute($user);

        if ($landingRoute === null) {
            Auth::logout();

            return back()
                ->withErrors(['username' => 'Your role doesn\'t have access to any page yet. Contact your administrator.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended(route($landingRoute));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
