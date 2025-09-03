<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use McpDocs\Http\Controllers\McpController;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the MCP (Model Context Protocol) documentation
| service. This provides a JSON-RPC 2.0 endpoint for LLM clients to
| access API documentation.
|
*/

Route::post('/', [McpController::class, 'handle'])
    ->middleware(['mcp.auth'])
    ->name('mcp.handle');
