<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use App\Models\User;

class CreateTagAction
{
    public function handle(User $user, array $data): Tag
    {
        if ($user->tags()->where('name', $data['name'])->exists()) {
            throw new \InvalidArgumentException('Tag name already exists');
        }

        return $user->tags()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
        ]);
    }
}
