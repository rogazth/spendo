<?php

namespace App\Http\Controllers;

use App\Actions\Tags\CreateTagAction;
use App\Actions\Tags\DeleteTagAction;
use App\Actions\Tags\UpdateTagAction;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $tags = $user->tags()->orderBy('name')->get();

        return Inertia::render('tags/index', [
            'tags' => $tags->map(fn ($tag) => [
                'id' => $tag->id,
                'uuid' => $tag->uuid,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->toArray(),
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $user = Auth::user();

        try {
            app(CreateTagAction::class)->handle($user, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Etiqueta creada.');
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        if ($tag->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            app(UpdateTagAction::class)->handle($tag, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        if ($tag->user_id !== Auth::id()) {
            abort(403);
        }

        app(DeleteTagAction::class)->handle($tag);

        return redirect()->back()->with('success', 'Etiqueta eliminada.');
    }
}
