<?php

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

final class EnvValidationException extends RuntimeException implements HTTPExceptionInterface
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct("Environment configuration is invalid:\n- " . implode("\n- ", $errors), 503);
    }
}
