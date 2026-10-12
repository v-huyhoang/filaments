<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['en', 'vi'], true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->route('filament.admin.pages.dashboard');
    }
}
