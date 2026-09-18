<?php

namespace App\Commands;

use App\Services\EnvFileManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\EnvSchema;

final class EnvDump extends BaseCommand
{
    protected $group = 'Environment';
    protected $name = 'env:dump';
    protected $description = 'Regenerates .env.example from the environment schema.';

    public function run(array $params): int
    {
        $path = ROOTPATH . '.env.example';
        $contents = new EnvFileManager(new EnvSchema()->schema)->renderExample();
        if (file_put_contents($path, $contents) === false) {
            CLI::error('Unable to write ' . $path);

            return EXIT_ERROR;
        }

        CLI::write('Generated ' . $path, 'green');

        return EXIT_SUCCESS;
    }
}
