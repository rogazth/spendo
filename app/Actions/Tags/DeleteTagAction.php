<?php

namespace App\Actions\Tags;

use App\Models\Tag;

class DeleteTagAction
{
    public function handle(Tag $tag): void
    {
        $tag->delete();
    }
}
