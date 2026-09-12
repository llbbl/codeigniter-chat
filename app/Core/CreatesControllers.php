<?php

namespace App\Core;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use LogicException;

trait CreatesControllers
{
    protected function createController(): Controller
    {
        if (! is_string($this->controller)) {
            throw new LogicException('A controller class name is required.');
        }

        if (! $this->request instanceof RequestInterface || ! $this->response instanceof ResponseInterface) {
            throw new LogicException('The HTTP request and response must be initialized before the controller.');
        }

        $controller = Services::controller($this->controller);
        $controller->initController($this->request, $this->response, Services::logger());

        $this->benchmark->stop('controller_constructor');

        return $controller;
    }
}
