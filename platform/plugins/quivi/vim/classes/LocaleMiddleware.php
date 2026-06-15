<?php namespace Quivi\Vim\Classes;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Winter\Storm\Support\Facades\Config;

class LocaleMiddleware
{
    protected const SESSION_KEY = 'vim_locale';
    protected const SUPPORTED_LOCALES = ['it', 'en', 'ar'];

    public function handle(Request $request, Closure $next)
    {
        if ($this->isBackendRequest($request)) {
            return $next($request);
        }

        $locale = $this->normalizeLocale($request->query('lang'));

        if ($locale && $request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $locale);
        }

        if (!$locale && $request->hasSession()) {
            $locale = $this->normalizeLocale($request->session()->get(self::SESSION_KEY));
        }

        if (!$locale) {
            $locale = $this->normalizeLocale($request->getPreferredLanguage(self::SUPPORTED_LOCALES));
        }

        App::setLocale($locale ?: 'it');

        return $next($request);
    }

    protected function isBackendRequest(Request $request): bool
    {
        $backendUri = trim((string) Config::get('cms.backendUri', 'backend'), '/');
        $firstSegment = trim((string) $request->segment(1), '/');

        return $backendUri !== '' && strcasecmp($firstSegment, $backendUri) === 0;
    }

    protected function normalizeLocale($locale): ?string
    {
        if (!is_string($locale) || $locale === '') {
            return null;
        }

        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $locale = strtok($locale, '-') ?: $locale;

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : null;
    }
}
