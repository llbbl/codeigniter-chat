<?php

namespace Tests\Support;

use CodeIgniter\CodeIgniter;
use Config\App;
use Config\Autoload;
use Config\Modules;

trait UsesApplication
{
    protected function createApplication(): CodeIgniter
    {
        service('autoloader')->initialize(new Autoload(), new Modules());

        $app = new TestApplication(new App());
        $app->initialize();

        return $app;
    }
}
