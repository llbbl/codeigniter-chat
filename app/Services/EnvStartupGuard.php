<?php

namespace App\Services;

use App\Exceptions\EnvValidationException;

final class EnvStartupGuard
{
    public function __construct(private readonly EnvValidator $validator)
    {
    }

    public function assertValid(): void
    {
        $errors = $this->validator->validate();
        if ($errors !== []) {
            throw new EnvValidationException($errors);
        }
    }
}
