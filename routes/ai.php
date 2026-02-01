<?php

use App\Mcp\Servers\SpendoServer;
use Laravel\Mcp\Facades\Mcp;

// OAuth routes for MCP authentication
// This enables the "needs authentication" flow that opens a browser window
Mcp::oauthRoutes();

// Web server accessible via HTTP (for remote AI clients)
// Protected with Passport OAuth authentication
// URL: /api/mcp
Mcp::web('/api/mcp', SpendoServer::class)
    ->middleware(['auth:api']);

// Local server for CLI usage (e.g., Claude Code)
Mcp::local('spendo', SpendoServer::class);
