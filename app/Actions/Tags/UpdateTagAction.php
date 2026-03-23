<?php

namespace App\Actions\Tags;

use App\Models\Tag;

class UpdateTagAction
{
    public function handle(Tag $tag, array $data): Tag
    {
        if (isset($data['name']) && $data['name'] !== $tag->name) {
            $duplicate = $tag->user->tags()
                ->where('name', $data['name'])
                ->where('id', '!=', $tag->id)
                ->exists();

            if ($duplicate) {
                throw new \InvalidArgumentException('Tag name already exists');
            }
        }

        $tag->update(array_filter($data, fn ($v) => $v !== null));

        return $tag;
    }
}
