<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserSettingsUpdateRequest;
use App\Http\Resources\UserSettingsResource;
use App\Models\Currency;
use App\Models\UserSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserSettingsController extends Controller
{
    public function edit(): Response
    {
        $user = Auth::user();
        $settings = $user->settings ?? new UserSettings([
            'default_currency' => 'CLP',
            'budget_cycle_start_day' => 1,
            'timezone' => 'America/Santiago',
        ]);

        return Inertia::render('settings/preferences', [
            'settings' => new UserSettingsResource($settings),
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get(['code', 'name', 'locale'])
                ->map(fn (Currency $currency) => [
                    'value' => $currency->code,
                    'label' => "{$currency->name} ({$currency->code})",
                ])
                ->values(),
            'timezones' => [
                ['value' => 'America/Santiago', 'label' => 'Santiago (Chile)'],
                ['value' => 'America/New_York', 'label' => 'Nueva York (EE.UU.)'],
                ['value' => 'America/Los_Angeles', 'label' => 'Los Ángeles (EE.UU.)'],
                ['value' => 'America/Mexico_City', 'label' => 'Ciudad de México'],
                ['value' => 'America/Buenos_Aires', 'label' => 'Buenos Aires (Argentina)'],
                ['value' => 'America/Bogota', 'label' => 'Bogotá (Colombia)'],
                ['value' => 'America/Lima', 'label' => 'Lima (Perú)'],
                ['value' => 'Europe/Madrid', 'label' => 'Madrid (España)'],
                ['value' => 'Europe/London', 'label' => 'Londres (Reino Unido)'],
                ['value' => 'Europe/Paris', 'label' => 'París (Francia)'],
                ['value' => 'UTC', 'label' => 'UTC'],
            ],
        ]);
    }

    public function update(UserSettingsUpdateRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'default_currency' => $validated['default_currency'],
                'budget_cycle_start_day' => $validated['budget_cycle_start_day'],
                'timezone' => $validated['timezone'],
            ]
        );

        return redirect()
            ->route('user-settings.edit')
            ->with('success', 'Preferencias actualizadas correctamente.');
    }
}
