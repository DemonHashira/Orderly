<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class FormatCommand extends Command
{
    protected $signature = 'format
        {--dirty : Only fix dirty (modified) files}
        {--all : Also run web lint:fix and format}';

    protected $description = 'Run Pint to fix PHP formatting (optionally run web lint:fix and format)';

    public function handle(): int
    {
        $dirty = $this->option('dirty');
        $all = $this->option('all');

        $pintArgs = $dirty ? ['--dirty'] : [];
        $result = Process::path(base_path())
            ->run(array_merge(['./vendor/bin/pint'], $pintArgs));

        if ($result->failed()) {
            $this->error('Pint failed.');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        $this->info('Pint completed.');

        if ($all) {
            $webPath = base_path('../web');
            if (! is_dir($webPath)) {
                $this->warn('Web directory not found, skipping web format.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->info('Running web lint:fix...');
            $lintResult = Process::path($webPath)->run(['npm', 'run', 'lint:fix']);
            if ($lintResult->failed()) {
                $this->error('Web lint:fix failed.');
                $this->line($lintResult->errorOutput());

                return self::FAILURE;
            }

            $this->info('Running web format...');
            $formatResult = Process::path($webPath)->run(['npm', 'run', 'format']);
            if ($formatResult->failed()) {
                $this->error('Web format failed.');
                $this->line($formatResult->errorOutput());

                return self::FAILURE;
            }

            $this->info('Web format completed.');
        }

        return self::SUCCESS;
    }
}
