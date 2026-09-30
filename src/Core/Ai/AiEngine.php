<?php

namespace LaraSlice\Core\Ai;

use LaraSlice\Core\Discovery\SliceManager;

class AiEngine
{
    protected SliceManager $sliceManager;

    public function __construct(SliceManager $sliceManager)
    {
        $this->sliceManager = $sliceManager;
    }

    /**
     * Inspect all active slices and generate an AI context prompt
     * describing available business objects, workflows, and endpoints.
     */
    public function getSystemContext(): array
    {
        $slices = $this->sliceManager->getActiveSlices();
        $context = [
            'framework' => 'LaraSlice Enterprise',
            'version'   => '1.0.0',
            'slices'    => [],
        ];

        foreach ($slices as $name => $manifest) {
            $context['slices'][$name] = [
                'title'        => $manifest->title,
                'description'  => $manifest->description,
                'version'      => $manifest->version,
                'permissions'  => $manifest->permissions,
                'dependencies' => $manifest->dependencies,
            ];
        }

        return $context;
    }

    /**
     * Export registered slice tools for AI Function Calling (OpenAI / Gemini / Anthropic / MCP).
     */
    public function getAvailableTools(): array
    {
        return [
            [
                'name'        => 'list_slices',
                'description' => 'List all active business slices and modules in the application.',
                'parameters'  => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'generate_slice',
                'description' => 'Scaffold a new vertical slice module with full CRUD, models, and BlatUI views.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['name'],
                    'properties' => [
                        'name'     => ['type' => 'string', 'description' => 'Name of the slice (e.g. Invoice, Project, Ticket)'],
                        'fields'   => ['type' => 'string', 'description' => 'Comma-separated fields (e.g. title:string,amount:decimal,status:string)'],
                        'workflow' => ['type' => 'boolean', 'description' => 'Whether to include state machine workflow engine'],
                    ],
                ],
            ],
        ];
    }
}
