<?php

namespace App\Commands;

use App\Services\EnvValidator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\EnvSchema;

final class EnvCheck extends BaseCommand
{
    protected $group = 'Environment';
    protected $name = 'env:check';
    protected $description = 'Validates the current environment configuration.';

    public function run(array $params): int
    {
        $errors = new EnvValidator(new EnvSchema()->schema)->validate();
        if ($errors === []) {
            CLI::write('Environment configuration is valid.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::error('Environment configuration is invalid:');
        foreach ($errors as $error) {
            CLI::error('- ' . $error);
        }

        return EXIT_ERROR;
    }
}
