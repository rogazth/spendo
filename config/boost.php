<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Guidelines
    |--------------------------------------------------------------------------
    |
    | Claude Code reads CLAUDE.md, which only imports AGENTS.md. Writing the
    | Claude guidelines to AGENTS.md keeps a single copy for every agent.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

];
