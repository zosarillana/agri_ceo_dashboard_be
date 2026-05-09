<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name}';

    protected $description = 'Create a new service class';

    public function handle()
    {
        $name = $this->argument('name');

        // Convert slashes for folders
        $name = str_replace('\\', '/', $name);

        $path = app_path("Services/{$name}.php");

        // Get directory path
        $directory = dirname($path);

        // Create directory recursively
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Check if service already exists
        if (File::exists($path)) {
            $this->error('Service already exists!');

            return;
        }

        // Get class name only
        $className = class_basename($name);

        // Build namespace
        $namespace = 'App\\Services';

        $folders = explode('/', dirname($name));

        if ($folders[0] !== '.') {
            $namespace .= '\\'.implode('\\', $folders);
        }

        // Service template
        $stub = <<<PHP
<?php

namespace {$namespace};

class {$className}
{
    //
}
PHP;

        File::put($path, $stub);

        $this->info("Service {$name} created successfully.");
    }
}
