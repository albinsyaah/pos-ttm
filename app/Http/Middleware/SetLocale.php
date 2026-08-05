<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * The locales supported by the application, keyed by their locale code.
     *
     * @var array<string, string>
     */
    public const SUPPORTED_LOCALES = ['en', 'id'];

    /**
     * Handle an incoming request.
     *
     * Applies the user's preferred language (stored in the session by
     * LanguageController) to every request so all __()/@lang() calls
     * resolve to the right translation for the rest of the app lifecycle.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale'));

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
