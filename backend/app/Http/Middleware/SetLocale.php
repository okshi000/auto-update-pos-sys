<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to set application locale based on Accept-Language header
 * or X-Locale header. Supports Arabic (ar) and English (en).
 */
class SetLocale
{
    /**
     * Supported locales.
     */
    protected array $supportedLocales = ['en', 'ar'];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);
        App::setLocale($locale);

        $response = $next($request);

        // Add locale info to response headers
        if ($response instanceof Response) {
            $response->headers->set('Content-Language', $locale);
            $response->headers->set('X-Locale', $locale);
            $response->headers->set('X-Text-Direction', $locale === 'ar' ? 'rtl' : 'ltr');
        }

        return $response;
    }

    /**
     * Determine the locale from the request.
     */
    protected function determineLocale(Request $request): string
    {
        // Priority 1: X-Locale header (explicit override)
        if ($request->hasHeader('X-Locale')) {
            $locale = strtolower($request->header('X-Locale'));
            if (in_array($locale, $this->supportedLocales)) {
                return $locale;
            }
        }

        // Priority 2: Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = $this->parseAcceptLanguage($acceptLanguage);
            if ($locale) {
                return $locale;
            }
        }

        // Priority 3: Query parameter (for testing/debugging)
        if ($request->has('locale')) {
            $locale = strtolower($request->query('locale'));
            if (in_array($locale, $this->supportedLocales)) {
                return $locale;
            }
        }

        // Default to app config
        return config('app.locale', 'en');
    }

    /**
     * Parse Accept-Language header and return best match.
     */
    protected function parseAcceptLanguage(string $header): ?string
    {
        $locales = [];
        
        // Parse: ar-LY,ar;q=0.9,en-US;q=0.8,en;q=0.7
        $parts = explode(',', $header);
        
        foreach ($parts as $part) {
            $part = trim($part);
            $langParts = explode(';', $part);
            $lang = strtolower(substr(trim($langParts[0]), 0, 2));
            
            $quality = 1.0;
            if (isset($langParts[1]) && preg_match('/q=([\d.]+)/', $langParts[1], $matches)) {
                $quality = (float) $matches[1];
            }
            
            if (in_array($lang, $this->supportedLocales)) {
                $locales[$lang] = $quality;
            }
        }

        if (empty($locales)) {
            return null;
        }

        arsort($locales);
        return array_key_first($locales);
    }
}
