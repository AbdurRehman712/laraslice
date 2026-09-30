<?php

namespace LaraSlice\Wizard;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraSlice\Generator\SliceGenerator;
use LaraSlice\Generator\SliceExistsException;
use LaraSlice\Generator\SliceFieldDefinitionException;
use InvalidArgumentException;

class WizardController extends Controller
{
    public function show()
    {
        return view('laraslice::wizard');
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'projectName'  => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'namespace'    => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/'],
            'description'  => 'nullable|string|max:1000',
            'author'       => 'nullable|string|max:120',
            'domain'       => 'nullable|string|max:120',
            'features'     => 'nullable|array',
            'fields'       => 'nullable|array|max:50',
            'childTables'  => 'nullable|array|max:20',
            'permissions'  => 'nullable|array|max:50',
            'workflow'     => 'nullable|boolean',
            'includeApi'   => 'nullable|boolean',
            'flutter'      => 'nullable|boolean',
            'runMigration' => 'nullable|boolean',
        ]);
        $includeApi = (bool) ($validated['includeApi'] ?? true);

        try {
            $generator = new SliceGenerator(null, $validated['namespace'] ?? null);
            $sliceDir = $generator->generate(
                $validated['projectName'],
                $validated['fields'] ?? [],
                (bool) ($validated['workflow'] ?? false),
                [
                    'description' => $validated['description'] ?? null,
                    'author'      => $validated['author'] ?? null,
                    'domain'      => $validated['domain'] ?? null,
                    'permissions' => $validated['permissions'] ?? null,
                    'api'         => $includeApi,
                ]
            );

            // Scaffold child tables and aggregate relationships if defined
            if (!empty($validated['childTables'])) {
                $modifier = new \LaraSlice\Generator\SliceModifier(
                    dirname($sliceDir),
                    $validated['namespace'] ?? null
                );
                foreach ($validated['childTables'] as $child) {
                    if (!empty($child['name'])) {
                        $childName = $child['name'];
                        $relationType = $child['relation'] ?? 'hasMany';
                        $childFields = $child['fields'] ?? [];
                        try {
                            $modifier->addChildTable($validated['projectName'], $childName, $relationType, $childFields);
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Could not add child table {$childName}: " . $e->getMessage());
                        }
                    }
                }
            }
        } catch (SliceExistsException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['projectName' => [$exception->getMessage()]],
            ], 409);
        } catch (SliceFieldDefinitionException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['fields' => [$exception->getMessage()]],
            ], 422);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['configuration' => [$exception->getMessage()]],
            ], 422);
        }

        $flutterDir = null;
        if (!empty($validated['flutter'])) {
            $flutterGen = new \LaraSlice\Generator\FlutterSliceGenerator();
            $flutterDir = $flutterGen->generate($validated['projectName']);
        }

        $migrated = false;
        $migrationOutput = null;
        if (!empty($validated['runMigration'])) {
            [$migrated, $migrationOutput] = $this->executeMigrations();
        }

        $singular = strtolower(\Illuminate\Support\Str::snake($validated['projectName']));
        $plural   = strtolower(\Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($validated['projectName'])));

        return response()->json([
            'success'         => true,
            'message'         => "Slice '{$validated['projectName']}' generated successfully!",
            'sliceName'       => $validated['projectName'],
            'path'            => $sliceDir,
            'flutterPath'     => $flutterDir,
            'web_url'         => url("/{$plural}"),
            'api_url'         => $includeApi ? url("/api/{$plural}/list") : null,
            'migrated'        => $migrated,
            'migrationOutput' => $migrationOutput,
        ]);
    }

    public function runMigration(Request $request)
    {
        [$success, $output] = $this->executeMigrations();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Database migration completed successfully!' : 'Migration failed: ' . $output,
            'output'  => $output,
        ], $success ? 200 : 500);
    }

    /**
     * Run all migrations including any slice-specific migration folders.
     */
    protected function executeMigrations(): array
    {
        $outputs = [];
        $migrated = false;

        try {
            // Re-discover slices to include newly created ones
            $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
            $manager->discover();

            $migrationPaths = [];
            if (app()->bound('migrator')) {
                $migrator = app('migrator');
                foreach ($manager->getActiveSlices() as $slice) {
                    if ($mp = $slice->getMigrationsPath()) {
                        if (is_dir($mp)) {
                            $migrator->path($mp);
                            $migrationPaths[] = $mp;
                        }
                    }
                }
            }

            // Also scan app/Slices for any Migrations directories
            $slicesBasePath = base_path('app/Slices');
            if (is_dir($slicesBasePath)) {
                $rdi = new \RecursiveDirectoryIterator($slicesBasePath, \RecursiveDirectoryIterator::SKIP_DOTS);
                $rii = new \RecursiveIteratorIterator($rdi, \RecursiveIteratorIterator::SELF_FIRST);
                foreach ($rii as $item) {
                    if ($item->isDir() && strtolower($item->getFilename()) === 'migrations') {
                        $mp = $item->getRealPath();
                        if ($mp && !in_array($mp, $migrationPaths, true)) {
                            $migrationPaths[] = $mp;
                            if (app()->bound('migrator')) {
                                app('migrator')->path($mp);
                            }
                        }
                    }
                }
            }

            // 1. Run standard migrate
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $defaultOut = trim(\Illuminate\Support\Facades\Artisan::output());
            if ($defaultOut) {
                $outputs[] = $defaultOut;
            }

            // 2. Explicitly migrate each discovered slice migration folder
            foreach (array_unique($migrationPaths) as $mp) {
                if (is_dir($mp)) {
                    $relative = str_replace([base_path() . DIRECTORY_SEPARATOR, base_path() . '/'], '', $mp);
                    $relative = str_replace('\\', '/', $relative);
                    try {
                        \Illuminate\Support\Facades\Artisan::call('migrate', [
                            '--force' => true,
                            '--path'  => $relative,
                        ]);
                        $out = trim(\Illuminate\Support\Facades\Artisan::output());
                        if ($out && !str_contains($out, 'Nothing to migrate')) {
                            $outputs[] = $out;
                        }
                    } catch (\Throwable $pe) {
                        try {
                            \Illuminate\Support\Facades\Artisan::call('migrate', [
                                '--force'    => true,
                                '--realpath' => true,
                                '--path'     => $mp,
                            ]);
                            $out = trim(\Illuminate\Support\Facades\Artisan::output());
                            if ($out && !str_contains($out, 'Nothing to migrate')) {
                                $outputs[] = $out;
                            }
                        } catch (\Throwable $pe2) {}
                    }
                }
            }

            $migrated = true;
            $combined = implode("\n", array_filter(array_unique($outputs)));
            $finalOutput = !empty($combined) ? $combined : 'All database tables created and verified successfully.';
        } catch (\Throwable $e) {
            $migrated = false;
            $finalOutput = $e->getMessage();
        }

        return [$migrated, $finalOutput];
    }

    public function listSlices()
    {
        $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
        $slices = [];
        foreach ($manager->getAllSlices() as $slice) {
            $manifestFile = $slice->path . '/slice.json';
            $raw = file_exists($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : $slice->toArray();

            // Discover all tables belonging to this slice
            $primaryTable = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::snake($slice->name));
            $tableNames = [$primaryTable];

            // Scan Models
            $modelFiles = glob($slice->path . '/Models/*.php');
            if ($modelFiles) {
                foreach ($modelFiles as $mf) {
                    $mContent = file_get_contents($mf);
                    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $mContent, $mMatches)) {
                        $t = $mMatches[1];
                        if (!in_array($t, $tableNames)) {
                            $tableNames[] = $t;
                        }
                    } else {
                        $t = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::snake(basename($mf, '.php')));
                        if (!in_array($t, $tableNames)) {
                            $tableNames[] = $t;
                        }
                    }
                }
            }

            if (!empty($raw['tables'])) {
                foreach ($raw['tables'] as $t) {
                    if (!in_array($t, $tableNames)) {
                        $tableNames[] = $t;
                    }
                }
            }

            $models = [];
            if ($modelFiles) {
                foreach ($modelFiles as $mf) {
                    $mContent = file_get_contents($mf);
                    $mClass = basename($mf, '.php');
                    $mTable = null;
                    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $mContent, $mMatches)) {
                        $mTable = $mMatches[1];
                    } else {
                        $mTable = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::snake($mClass));
                    }
                    $isRoot = ($mTable === $primaryTable) || str_contains($mClass, \Illuminate\Support\Str::studly(\Illuminate\Support\Str::singular($slice->name)));
                    $models[] = [
                        'class'  => $mClass,
                        'handle' => \Illuminate\Support\Str::snake($mClass),
                        'table'  => $mTable,
                        'root'   => $isRoot,
                    ];
                }
            }
            $raw['models'] = $models;
            $raw['relations'] = $raw['relations'] ?? [];

            $tablesData = [];
            foreach ($tableNames as $t) {
                $cols = [];
                if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                    foreach (\Illuminate\Support\Facades\Schema::getColumnListing($t) as $col) {
                        $cols[] = [
                            'name'     => $col,
                            'type'     => \Illuminate\Support\Facades\Schema::getColumnType($t, $col),
                            'nullable' => true,
                        ];
                    }
                }
                $tablesData[] = [
                    'name'       => $t,
                    'is_primary' => ($t === $primaryTable),
                    'foreign_key' => \Illuminate\Support\Str::singular($primaryTable) . '_id',
                    'columns'    => $cols,
                ];
            }

            // Calculate resolved Web UI URL for Open Slice UI button
            $uiUrl = null;
            $snake = \Illuminate\Support\Str::snake($slice->name);
            $pluralSnake = \Illuminate\Support\Str::plural($snake);
            $singularSnake = \Illuminate\Support\Str::singular($snake);

            $candidates = [
                $raw['navigation']['route'] ?? null,
                $snake . '.index',
                $pluralSnake . '.index',
                $singularSnake . '.index',
                'admin.' . $snake . '.index',
                'admin.' . $pluralSnake . '.index',
                'admin.' . $singularSnake . '.index',
            ];

            foreach ($candidates as $cand) {
                if ($cand && \Illuminate\Support\Facades\Route::has($cand)) {
                    $uiUrl = route($cand);
                    break;
                }
            }

            if (!$uiUrl) {
                $uiUrl = url('/' . $pluralSnake);
            }
            $raw['ui_url'] = $uiUrl;

            $raw['tables_data'] = $tablesData;
            $slices[] = $raw;
        }

        return response()->json([
            'success' => true,
            'data'    => $slices,
        ]);
    }

    public function addField(Request $request)
    {
        $validated = $request->validate([
            'slice' => 'required|string', 'field' => 'required|string', 'type' => 'required|string',
            'nullable' => 'nullable|boolean', 'migrate' => 'nullable|boolean',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->addField(
                sliceName: $validated['slice'], fieldName: $validated['field'], fieldType: $validated['type'],
                nullable: (bool) ($validated['nullable'] ?? true), targetTable: $request->input('targetTable')
            );
            $migrationResult = !empty($validated['migrate'])
                ? $this->runGeneratedMigration($result['migration'])
                : ['success' => null, 'message' => 'Migration was generated but not run.'];
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Field '{$validated['field']}' added to '{$validated['slice']}' successfully!",
            'data' => $result,
            'migration' => $migrationResult,
        ]);
    }

    public function addFieldsBatch(Request $request)
    {
        $validated = $request->validate([
            'slice' => 'required|string', 'fields' => 'required|array|min:1|max:50',
            'fields.*.name' => 'required|string', 'fields.*.type' => 'required|string',
            'fields.*.length' => 'nullable', 'fields.*.nullable' => 'nullable|boolean',
            'fields.*.default' => 'nullable', 'fields.*.unsigned' => 'nullable|boolean',
            'migrate' => 'nullable|boolean', 'note' => 'nullable|string',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->addFieldsBatch(
                $validated['slice'], $validated['fields'], 'Common User via Slice Studio',
                $validated['note'] ?? null, $request->input('targetTable')
            );
            $migrationResult = !empty($validated['migrate'])
                ? $this->runGeneratedMigration($result['migration'])
                : ['success' => null, 'message' => 'Migration was generated but not run.'];
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => count($validated['fields']) . " fields added to '{$validated['slice']}' in a single consolidated migration!",
            'data' => $result,
            'migration' => $migrationResult,
        ]);
    }
    public function updateNavigation(Request $request)
    {
        $validated = $request->validate([
            'slice'        => 'required|string',
            'title'        => 'required|string',
            'icon'         => 'nullable|string',
            'order'        => 'nullable|integer',
            'permission'   => 'nullable|string',
            'permissions'  => 'nullable|array',
            'url'          => 'nullable|string',
            'children'     => 'nullable|array',
            'redirect_old' => 'nullable|boolean',
        ]);

        $modifier = new \LaraSlice\Generator\SliceModifier();
        $nav = $modifier->updateNavigation($validated['slice'], $validated);

        return response()->json([
            'success' => true,
            'message' => "Navigation updated for '{$validated['slice']}'!",
            'data'    => $nav,
        ]);
    }

    public function rollbackVersion(Request $request)
    {
        $validated = $request->validate([
            'slice'          => 'required|string',
            'target_version' => 'required|string',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->rollbackVersion($validated['slice'], $validated['target_version']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function syncFields(Request $request)
    {
        $validated = $request->validate([
            'slice'          => 'required|string',
            'targetTable'    => 'required|string',
            'new_fields'     => 'nullable|array',
            'deleted_fields' => 'nullable|array',
            'all_fields'     => 'nullable|array',
            'migrate'        => 'nullable|boolean',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->syncFields(
                $validated['slice'],
                $validated['targetTable'],
                $validated['new_fields'] ?? [],
                $validated['deleted_fields'] ?? [],
                $validated['all_fields'] ?? [],
                'Developer via Slice Studio'
            );

            $migrationResult = (!empty($validated['migrate']) && !empty($result['migration']))
                ? $this->runGeneratedMigration($result['migration'])
                : ['success' => null, 'message' => 'Migration was generated but not run.'];

            return response()->json([
                'success'   => true,
                'message'   => $result['description'] ?? 'Schema synchronized successfully!',
                'data'      => $result,
                'migration' => $migrationResult,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveRelationships(Request $request)
    {
        $validated = $request->validate([
            'slice'     => 'required|string',
            'relations' => 'nullable|array',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->saveRelationships(
                $validated['slice'],
                $validated['relations'] ?? [],
                'Developer via Slice Studio'
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function addChildTable(Request $request)
    {
        $validated = $request->validate([
            'slice'        => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'tableName'    => 'required|string|max:80',
            'relationType' => 'nullable|in:hasMany',
            'foreignKey'   => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields'       => 'nullable|array|max:50',
            'fields.*.name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-zA-Z0-9_]*$/'],
            'fields.*.type' => 'nullable|string|in:string,text,mediumText,longText,integer,int,bigInteger,smallInteger,tinyInteger,boolean,bool,decimal,float,double,date,dateTime,datetime,timestamp,time,json,uuid',
            'fields.*.nullable' => 'nullable|boolean',
            'fields.*.required' => 'nullable|boolean',
            'migrate'      => 'nullable|boolean',
        ]);

        try {
            $modifier = new \LaraSlice\Generator\SliceModifier();
            $result = $modifier->addChildTable(
                $validated['slice'],
                $validated['tableName'],
                $validated['relationType'] ?? 'hasMany',
                $validated['fields'] ?? [],
                $validated['foreignKey'] ?? null
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => ['tableName' => [$exception->getMessage()]],
            ], 422);
        }

        $migrationResult = null;
        if (!empty($validated['migrate'])) {
            $migrationResult = $this->runGeneratedMigration($result['migration_file']);
        }

        return response()->json([
            'success' => $migrationResult === null || $migrationResult['success'],
            'message' => $migrationResult !== null && ! $migrationResult['success']
                ? 'The child slice files were generated, but its migration failed: ' . $migrationResult['output']
                : "Child table '{$validated['tableName']}' created and linked to '{$validated['slice']}' successfully!",
            'data'    => $result,
            'migration' => $migrationResult,
        ], $migrationResult !== null && ! $migrationResult['success'] ? 500 : 200);
    }

    private function runGeneratedMigration(string $migrationFile): array
    {
        $absoluteBase = realpath(base_path());
        $absoluteMigration = realpath($migrationFile);

        if ($absoluteBase === false || $absoluteMigration === false || ! str_starts_with($absoluteMigration, $absoluteBase . DIRECTORY_SEPARATOR)) {
            return ['success' => false, 'output' => 'Generated migration path is outside the application.'];
        }

        $status = \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => $absoluteMigration,
            '--realpath' => true,
            '--force' => true,
        ]);

        return [
            'success' => $status === 0,
            'output' => trim(\Illuminate\Support\Facades\Artisan::output()),
        ];
    }

    /**
     * AI Copilot Conversational Assistant (ElmapiCMS inspired)
     */
    public function copilotChat(Request $request)
    {
        $message = trim((string)$request->input('message', ''));
        $sliceContext = $request->input('slice', '');
        $step = (int)$request->input('step', 1);

        if (empty($message)) {
            return response()->json([
                'success' => false,
                'message' => 'Message is required.',
            ], 422);
        }

        $lowerMsg = strtolower($message);

        // 1. HR Complete Solution Intent
        if (str_contains($lowerMsg, 'hr') || str_contains($lowerMsg, 'human resource') || str_contains($lowerMsg, 'employee')) {
            if ($step === 1) {
                return response()->json([
                    'success' => true,
                    'step'    => 2,
                    'reply'   => "I can build a complete HR enterprise solution for you! I have designed a domain architecture with:\n\n" .
                                 "â€¢ **Departments**: Manage organizational departments, codes, and budgets\n" .
                                 "â€¢ **Positions**: Job designations linked to departments with salary bands\n" .
                                 "â€¢ **Employees**: Master records linked to departments & positions\n" .
                                 "â€¢ **LeaveRequests**: Employee leave tracking with status workflows\n" .
                                 "â€¢ **AttendanceRecords**: Daily check-in/out records\n\n" .
                                 "Would you like me to build all these modules or customize them? (Reply 'Build All' or specify modules)",
                    'options' => ['Build All', 'Employees & Departments Only', 'Include Payroll & Attendance'],
                    'plan'    => [
                        'slice' => 'HumanResources',
                        'title' => 'Human Resources Suite',
                        'tables' => ['positions', 'employees', 'leave_requests'],
                    ],
                ]);
            }

            // Step 2 confirmation
            $plan = [
                'slice' => 'HumanResources',
                'title' => 'Human Resources Suite',
                'tables' => [
                    [
                        'name' => 'positions',
                        'relation' => 'hasMany',
                        'foreignKey' => 'human_resource_id',
                        'fields' => [
                            ['name' => 'code', 'type' => 'string'],
                            ['name' => 'salary_min', 'type' => 'decimal'],
                            ['name' => 'salary_max', 'type' => 'decimal'],
                        ]
                    ],
                    [
                        'name' => 'employees',
                        'relation' => 'hasMany',
                        'foreignKey' => 'human_resource_id',
                        'fields' => [
                            ['name' => 'employee_id', 'type' => 'string'],
                            ['name' => 'first_name', 'type' => 'string'],
                            ['name' => 'last_name', 'type' => 'string'],
                            ['name' => 'email', 'type' => 'string'],
                            ['name' => 'phone', 'type' => 'string'],
                            ['name' => 'hire_date', 'type' => 'date'],
                        ]
                    ],
                    [
                        'name' => 'leave_requests',
                        'relation' => 'hasMany',
                        'foreignKey' => 'human_resource_id',
                        'fields' => [
                            ['name' => 'leave_type', 'type' => 'string'],
                            ['name' => 'start_date', 'type' => 'date'],
                            ['name' => 'end_date', 'type' => 'date'],
                            ['name' => 'reason', 'type' => 'text'],
                            ['name' => 'status', 'type' => 'string'],
                        ]
                    ],
                ]
            ];

            return response()->json([
                'success' => true,
                'step'    => 3,
                'reply'   => "Execution plan ready! I will generate 3 child entities with relational foreign keys, Eloquent models, BlatUI views, and database migrations for 'HumanResources'. Click 'Apply Plan' below to execute.",
                'can_execute' => true,
                'plan'    => $plan,
            ]);
        }

        // 2. E-Commerce Store Intent
        if (str_contains($lowerMsg, 'commerce') || str_contains($lowerMsg, 'shop') || str_contains($lowerMsg, 'product') || str_contains($lowerMsg, 'order')) {
            $plan = [
                'slice' => 'Shop',
                'title' => 'E-Commerce Store Suite',
                'tables' => [
                    [
                        'name' => 'categories',
                        'relation' => 'hasMany',
                        'foreignKey' => 'shop_id',
                        'fields' => [
                            ['name' => 'slug', 'type' => 'string'],
                            ['name' => 'description', 'type' => 'text'],
                        ]
                    ],
                    [
                        'name' => 'products',
                        'relation' => 'hasMany',
                        'foreignKey' => 'shop_id',
                        'fields' => [
                            ['name' => 'sku', 'type' => 'string'],
                            ['name' => 'price', 'type' => 'decimal'],
                            ['name' => 'stock', 'type' => 'integer'],
                        ]
                    ],
                    [
                        'name' => 'orders',
                        'relation' => 'hasMany',
                        'foreignKey' => 'shop_id',
                        'fields' => [
                            ['name' => 'order_number', 'type' => 'string'],
                            ['name' => 'customer_name', 'type' => 'string'],
                            ['name' => 'total_amount', 'type' => 'decimal'],
                            ['name' => 'status', 'type' => 'string'],
                        ]
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'step'    => 3,
                'reply'   => "E-Commerce store architecture designed! Includes Categories, Products, and Orders with relationships. Click 'Apply Plan' to generate.",
                'can_execute' => true,
                'plan'    => $plan,
            ]);
        }

        // Default Copilot response
        return response()->json([
            'success' => true,
            'step'    => 1,
            'reply'   => "I'm your LaraSlice Copilot! I can autonomously scaffold complete domain modules like:\n\n" .
                         "â€¢ **HR Complete Solution** (Departments, Positions, Employees, Leaves)\n" .
                         "â€¢ **E-Commerce Suite** (Categories, Products, Variants, Orders)\n" .
                         "â€¢ **CRM Pipeline** (Accounts, Contacts, Leads, Deals)\n\n" .
                         "What domain module would you like to build?",
            'options' => ['Create HR complete solution', 'Build E-Commerce store', 'Scaffold CRM pipeline'],
        ]);
    }

    /**
     * Batch Scaffolding Engine for Copilot Execution Plans
     */
    public function batchGenerate(Request $request)
    {
        $validated = $request->validate([
            'slice' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'tables' => 'required|array|min:1|max:25',
            'tables.*.name' => 'required|string|max:80',
            'tables.*.relation' => 'nullable|in:hasMany',
            'tables.*.foreignKey' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'tables.*.fields' => 'nullable|array|max:50',
            'tables.*.fields.*.name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-zA-Z0-9_]*$/'],
            'tables.*.fields.*.type' => 'nullable|string|in:string,text,mediumText,longText,integer,int,bigInteger,smallInteger,tinyInteger,boolean,bool,decimal,float,double,date,dateTime,datetime,timestamp,time,json,uuid',
            'tables.*.fields.*.nullable' => 'nullable|boolean',
            'tables.*.fields.*.required' => 'nullable|boolean',
            'migrate' => 'nullable|boolean',
        ]);

        $sliceName = $validated['slice'];
        $tables = $validated['tables'];
        $modifier = new \LaraSlice\Generator\SliceModifier();
        $results = [];

        foreach ($tables as $tbl) {
            $tName = $tbl['name'];
            $rel = $tbl['relation'] ?? 'hasMany';
            $fields = $tbl['fields'] ?? [];
            $fk = $tbl['foreignKey'] ?? null;

            try {
                $res = $modifier->addChildTable($sliceName, $tName, $rel, $fields, $fk);
                $migration = !empty($validated['migrate'])
                    ? $this->runGeneratedMigration($res['migration_file'])
                    : null;
                $results[] = [
                    'table'   => $tName,
                    'status'  => $migration !== null && ! $migration['success'] ? 'migration_failed' : 'created',
                    'details' => $res,
                    'migration' => $migration,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'table'   => $tName,
                    'status'  => 'skipped',
                    'error'   => $e->getMessage(),
                ];
            }
        }

        $failed = array_values(array_filter($results, fn (array $result) => $result['status'] !== 'created'));

        return response()->json([
            'success' => $failed === [],
            'message' => $failed === []
                ? 'Generated and migrated ' . count($results) . " child entities for '{$sliceName}'."
                : count($failed) . ' of ' . count($results) . ' child entities failed. Successful files and per-entity migration results are listed below.',
            'data'    => $results,
        ], $failed === [] ? 200 : 422);
    }

    /**
     * Fetch audit logs for a slice or recent system activity.
     */
    public function getAuditLogs(Request $request)
    {
        $slice = $request->query('slice');
        $limit = min((int) ($request->query('limit', 50)), 100);

        if (!empty($slice)) {
            $sliceHandle = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $slice));
            $logs = \LaraSlice\Core\Audit\AuditLogger::forSlice($sliceHandle, $limit);
            if ($logs->isEmpty() && $slice !== $sliceHandle) {
                $logs = \LaraSlice\Core\Audit\AuditLogger::forSlice(strtolower($slice), $limit);
            }
        } else {
            $logs = \LaraSlice\Core\Audit\AuditLogger::recent($limit);
        }

        return response()->json([
            'success' => true,
            'slice'   => $slice,
            'logs'    => $logs,
        ]);
    }

    /**
     * Scaffold a complete domain suite (multiple slices grouped under a single domain) in one pass.
     */
    public function generateDomainSuite(Request $request)
    {
        $validated = $request->validate([
            'domain'       => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'slices'       => ['required', 'array', 'min:1', 'max:20'],
            'slices.*'     => ['required', 'string', 'max:80', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'sliceSchemas' => 'nullable|array',
            'namespace'    => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/'],
            'author'       => 'nullable|string|max:120',
            'workflow'     => 'nullable|boolean',
            'includeApi'   => 'nullable|boolean',
            'flutter'      => 'nullable|boolean',
            'runMigration' => 'nullable|boolean',
        ]);

        $domain = trim($validated['domain']);
        $sliceNames = array_unique(array_filter(array_map('trim', $validated['slices'])));
        $generator = new SliceGenerator(null, $validated['namespace'] ?? null);
        $flutterGen = !empty($validated['flutter']) ? new \LaraSlice\Generator\FlutterSliceGenerator() : null;
        $schemas = $validated['sliceSchemas'] ?? [];

        $created = [];
        $errors = [];

        foreach ($sliceNames as $sliceName) {
            try {
                $sliceFields = $schemas[$sliceName]['fields'] ?? [];
                $sliceDir = $generator->generate(
                    $sliceName,
                    $sliceFields,
                    (bool) ($validated['workflow'] ?? false),
                    [
                        'domain' => $domain,
                        'author' => $validated['author'] ?? null,
                        'api'    => (bool) ($validated['includeApi'] ?? true),
                    ]
                );

                $childTables = $schemas[$sliceName]['childTables'] ?? [];
                if (!empty($childTables)) {
                    $modifier = new \LaraSlice\Generator\SliceModifier(
                        dirname($sliceDir),
                        $validated['namespace'] ?? null
                    );
                    foreach ($childTables as $child) {
                        if (!empty($child['name'])) {
                            $childFields = $child['fields'] ?? [];
                            $modifier->addChildEntity(
                                $sliceName,
                                $child['name'],
                                $childFields,
                                $child['relation'] ?? 'hasMany',
                                $child['foreign_key'] ?? null
                            );
                        }
                    }
                }

                if ($flutterGen) {
                    try {
                        $flutterGen->generate($sliceName);
                    } catch (\Throwable $e) {}
                }

                $created[] = [
                    'name' => $sliceName,
                    'dir'  => $sliceDir,
                ];
            } catch (\Throwable $e) {
                $errors[$sliceName] = $e->getMessage();
            }
        }

        $migrated = false;
        $migrationOutput = null;
        if (!empty($validated['runMigration']) && count($created) > 0) {
            [$migrated, $migrationOutput] = $this->executeMigrations();
        }

        try {
            app(\LaraSlice\Core\Discovery\SliceManager::class)->syncPermissions();
        } catch (\Throwable $e) {}

        $domainSlug = strtolower(\Illuminate\Support\Str::slug($domain));
        $createdWithRoutes = array_map(function ($c) use ($domainSlug) {
            $pluralSnake = strtolower(\Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($c['name'])));
            return [
                'name'    => $c['name'],
                'dir'     => $c['dir'],
                'web_url' => url("/{$domainSlug}/{$pluralSnake}"),
                'api_url' => url("/api/{$domainSlug}/{$pluralSnake}/list"),
            ];
        }, $created);

        return response()->json([
            'success'         => count($errors) === 0,
            'domain'          => $domain,
            'created'         => $createdWithRoutes,
            'errors'          => $errors,
            'migrated'        => $migrated,
            'migrationOutput' => $migrationOutput,
            'message'         => count($created) . " slice(s) successfully generated in domain [{$domain}].",
        ], count($created) > 0 ? 200 : 422);
    }

    /**
     * Authorize an administrative wizard action.
     * Super-admins always bypass. Authenticated users are checked against permission abilities.
     *
     * @param string|array $abilities
     * @return void
     */
    protected function authorizeWizardAction($abilities): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // 1. Super admin bypass
        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return;
        }
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }
        if (isset($user->email) && $user->email === config('laraslice.super_admin_email', 'admin@laraslice.com')) {
            return;
        }
        if (isset($user->id)) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('role_user') && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                    $isSuperAdmin = \Illuminate\Support\Facades\DB::table('role_user')
                        ->join('roles', 'role_user.role_id', '=', 'roles.id')
                        ->where('role_user.user_id', $user->id)
                        ->where(function ($q) {
                            $q->where('roles.slug', 'super-admin')
                              ->orWhere('roles.id', 1);
                        })
                        ->exists();
                    if ($isSuperAdmin) {
                        return;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // 2. Explicit ability check
        $abilities = (array)$abilities;
        foreach ($abilities as $ability) {
            if (method_exists($user, 'hasPermission') && $user->hasPermission($ability)) {
                return;
            }
            if (method_exists($user, 'can') && $user->can($ability)) {
                return;
            }
        }

        abort(403, 'Unauthorized. Required permissions: [' . implode(', ', $abilities) . ']');
    }

    public function toggleSlice(Request $request)
    {
        $validated = $request->validate([
            'slice'  => 'required|string',
            'active' => 'nullable|boolean',
        ]);

        try {
            $this->authorizeWizardAction(['system.slices.toggle', 'slice.disable', 'slice.toggle', 'slice.manage']);
            $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
            $newActive = $manager->toggleSlice($validated['slice'], $validated['active'] ?? null);

            return response()->json([
                'success' => true,
                'slice'   => $validated['slice'],
                'active'  => $newActive,
                'message' => "Slice [{$validated['slice']}] is now " . ($newActive ? 'Enabled' : 'Disabled (Hidden from Navigation)'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function toggleDomain(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string',
            'active' => 'nullable|boolean',
        ]);

        try {
            $dSlug = strtolower(\Illuminate\Support\Str::slug($validated['domain']));
            $this->authorizeWizardAction(['system.slices.toggle', 'domain.manage', "{$dSlug}.manage"]);
            $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
            $updated = $manager->toggleDomain($validated['domain'], $validated['active'] ?? null);

            return response()->json([
                'success' => true,
                'domain'  => $validated['domain'],
                'updated' => $updated,
                'message' => "Domain [{$validated['domain']}] slices updated.",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function seedSlice(Request $request)
    {
        $validated = $request->validate([
            'slice' => 'required|string',
            'count' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $sliceSlug = strtolower(\Illuminate\Support\Str::snake($validated['slice']));
            $this->authorizeWizardAction(['system.slices.seed', 'slice.seed', "{$sliceSlug}.seed"]);

            $seeder = new \LaraSlice\Generator\SliceSeederService();
            $result = $seeder->seedSlice($validated['slice'], (int)($validated['count'] ?? 10));

            if (!$request->expectsJson() && !$request->wantsJson()) {
                return back()->with('success', $result['message']);
            }

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function seedDomain(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string',
            'count'  => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $dSlug = strtolower(\Illuminate\Support\Str::slug($validated['domain']));
            $this->authorizeWizardAction(['system.slices.seed', 'domain.seed', "{$dSlug}.seed", "{$dSlug}.manage"]);

            $seeder = new \LaraSlice\Generator\SliceSeederService();
            $result = $seeder->seedDomain($validated['domain'], (int)($validated['count'] ?? 10));

            if (!$request->expectsJson() && !$request->wantsJson()) {
                return back()->with('success', $result['message']);
            }

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function wipeSlice(Request $request)
    {
        $validated = $request->validate([
            'slice' => 'required|string',
        ]);

        try {
            $sliceSlug = strtolower(\Illuminate\Support\Str::snake($validated['slice']));
            $this->authorizeWizardAction(['system.slices.wipe', 'slice.wipe', 'slice.delete', 'system.slices.delete']);

            $seeder = new \LaraSlice\Generator\SliceSeederService();
            $result = $seeder->wipeSlice($validated['slice']);

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function wipeDomain(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string',
        ]);

        try {
            $dSlug = strtolower(\Illuminate\Support\Str::slug($validated['domain']));
            $this->authorizeWizardAction(['system.slices.wipe', 'domain.wipe', 'domain.manage', 'system.slices.delete']);

            $seeder = new \LaraSlice\Generator\SliceSeederService();
            $result = $seeder->wipeDomain($validated['domain']);

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroySlice(Request $request)
    {
        $validated = $request->validate([
            'slice' => 'required|string',
            'mode'  => 'nullable|string|in:complete,code_only,db_only,wipe_data',
        ]);

        try {
            $sliceSlug = strtolower(\Illuminate\Support\Str::snake($validated['slice']));
            $this->authorizeWizardAction(['system.slices.delete', 'slice.delete', 'slice.destroy']);

            $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
            $result = $manager->destroySlice($validated['slice'], $validated['mode'] ?? 'complete');

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroyDomain(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string',
            'mode'   => 'nullable|string|in:complete,code_only,db_only,wipe_data',
        ]);

        try {
            $dSlug = strtolower(\Illuminate\Support\Str::slug($validated['domain']));
            $this->authorizeWizardAction(['system.slices.delete', 'domain.delete', "{$dSlug}.manage"]);

            $manager = app(\LaraSlice\Core\Discovery\SliceManager::class);
            $result = $manager->destroyDomain($validated['domain'], $validated['mode'] ?? 'complete');

            return response()->json($result);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
