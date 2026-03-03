<?php

namespace App\Http\Controllers;

use App\Http\Resources\InstrumentResource;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentController extends Controller
{
    public function index(): Response
    {
        $instruments = Auth::user()
            ->instruments()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('instruments/index', [
            'instruments' => $instruments->map(fn ($i) => (new InstrumentResource($i))->resolve())->values(),
        ]);
    }
}
