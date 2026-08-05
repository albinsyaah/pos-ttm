<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Switch the application's display language and return to where the
     * user came from. The choice is remembered in the session so it
     * persists across requests (see SetLocale middleware).
     */
    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
            Session::put('locale', $locale);
        }

        return back();
    }
}
