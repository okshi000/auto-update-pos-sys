<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Arr;

class LocaleController extends Controller
{
    /**
     * GET /api/locales
     * Get available locales and current locale settings.
     */
    public function index(): JsonResponse
    {
        $currentLocale = App::getLocale();
        $config = config('locales');

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $currentLocale,
                'supported' => $config['supported'],
                'is_rtl' => in_array($currentLocale, $config['rtl']),
                'direction' => in_array($currentLocale, $config['rtl']) ? 'rtl' : 'ltr',
                'names' => $config['names'],
                'date_format' => $config['date_formats'][$currentLocale] ?? $config['date_formats']['en'],
                'currency' => MoneyHelper::getCurrencyInfo(),
            ],
        ]);
    }

    /**
     * GET /api/translations
     * Get all translation strings for current locale.
     * Returns flattened key-value pairs for frontend consumption.
     */
    public function translations(): JsonResponse
    {
        $locale = App::getLocale();
        
        // Load and merge all translation files
        $translations = [];
        
        // Load UI translations (flat keys, ready to use)
        $uiPath = lang_path("{$locale}/ui.php");
        if (file_exists($uiPath)) {
            $translations = array_merge($translations, require $uiPath);
        }
        
        // Load API messages and flatten them with dot notation
        $messagesPath = lang_path("{$locale}/messages.php");
        if (file_exists($messagesPath)) {
            $messages = require $messagesPath;
            $flattened = Arr::dot($messages);
            $translations = array_merge($translations, $flattened);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'locale' => $locale,
                'direction' => in_array($locale, config('locales.rtl', [])) ? 'rtl' : 'ltr',
                'messages' => $translations,
            ],
        ]);
    }
}
