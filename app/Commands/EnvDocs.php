<?php

namespace App\Commands;

use App\Services\EnvFileManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\EnvSchema;

final class EnvDocs extends BaseCommand
{
    protected $group = 'Environment';
    protected $name = 'env:docs';
    protected $description = 'Regenerates environment configuration documentation.';

    public function run(array $params): int
    {
        $path = ROOTPATH . 'docs/configuration.md';
        $contents = new EnvFileManager(new EnvSchema()->schema)->renderDocumentation();
        if (file_put_contents($path, $contents) === false) {
            CLI::error('Unable to write ' . $path);

            return EXIT_ERROR;
        }

        CLI::write('Generated ' . $path, 'green');

        return EXIT_SUCCESS;
    }
}
