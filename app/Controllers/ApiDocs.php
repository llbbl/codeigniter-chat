<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class ApiDocs extends BaseController
{
    public function index(): string|ResponseInterface
    {
        $spec = file_get_contents(ROOTPATH . 'docs/openapi.yaml');
        if ($spec === false) {
            return $this->response
                ->setStatusCode(500)
                ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
                ->setBody('The OpenAPI specification is unavailable.');
        }

        return $this->respondWithView('api/docs', [
            'openapiBase64' => base64_encode($spec),
        ], cacheable: true, cacheTime: 300);
    }
}
