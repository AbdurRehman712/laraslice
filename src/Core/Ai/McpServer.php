<?php

namespace LaraSlice\Core\Ai;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class McpServer
{
    protected AiEngine $aiEngine;

    public function __construct(AiEngine $aiEngine)
    {
        $this->aiEngine = $aiEngine;
    }

    /**
     * Handle MCP Discovery / Tools request from IDE AI agents.
     */
    public function handle(Request $request): JsonResponse
    {
        $action = $request->input('method', 'tools/list');

        switch ($action) {
            case 'tools/list':
                return response()->json([
                    'tools' => $this->aiEngine->getAvailableTools(),
                ]);

            case 'context/inspect':
                return response()->json([
                    'context' => $this->aiEngine->getSystemContext(),
                ]);

            default:
                return response()->json([
                    'error' => 'Unknown MCP method',
                ], 400);
        }
    }
}
