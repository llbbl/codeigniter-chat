<?php

namespace App\Commands;

use App\Services\EnvFileManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\EnvSchema;

final class EnvDiff extends BaseCommand
{
    protected $group = 'Environment';
    protected $name = 'env:diff';
    protected $description = 'Checks .env.example for drift from the environment schema.';

    public function run(array $params): int
    {
        $path = ROOTPATH . '.env.example';
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            CLI::error('Unable to read ' . $path);

            return EXIT_ERROR;
        }

        $diff = new EnvFileManager(new EnvSchema()->schema)->diff($contents);
        if (! $diff['stale']) {
            CLI::write('.env.example matches the environment schema.', 'green');

            return EXIT_SUCCESS;
        }

        foreach ($diff['missing'] as $name) {
            CLI::error('Missing from .env.example: ' . $name);
        }
        foreach ($diff['unexpected'] as $name) {
            CLI::error('Not declared in EnvSchema: ' . $name);
        }
        if ($diff['missing'] === [] && $diff['unexpected'] === []) {
            CLI::error('.env.example content is stale. Run php spark env:dump.');
        }

        return EXIT_ERROR;
    }
}
