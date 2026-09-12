<?php

namespace Tests\Support;

use App\Core\CreatesControllers;
use CodeIgniter\Test\Mock\MockCodeIgniter;

final class TestApplication extends MockCodeIgniter
{
    use CreatesControllers;
}
