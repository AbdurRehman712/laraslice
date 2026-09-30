<?php

namespace LaraSlice\Commands;

use Illuminate\Console\Command;
use LaraSlice\Generator\FlutterSliceGenerator;

class SliceExportFlutterCommand extends Command
{
    protected $signature = 'slice:export-flutter {name : Name of the slice to export to Flutter}';
    protected $description = 'Generate matching Flutter client model, typed HTTP client, and UI views for a slice';

    public function handle(): int
    {
        $name = $this->argument('name');
        $this->info("📱 Exporting Flutter Client Slice for [{$name}]...");

        $flutterGen = new FlutterSliceGenerator();
        $targetDir = $flutterGen->generate($name);

        $this->line("<fg=green>✓</> Flutter Model: <comment>{$targetDir}/models/</comment>");
        $this->line("<fg=green>✓</> Flutter API Service: <comment>{$targetDir}/services/</comment>");
        $this->line("<fg=green>✓</> Flutter Listing & Form Views: <comment>{$targetDir}/views/</comment>");

        $this->newLine();
        $this->info("✨ Flutter slice exported successfully to [{$targetDir}]!");

        return Command::SUCCESS;
    }
}
