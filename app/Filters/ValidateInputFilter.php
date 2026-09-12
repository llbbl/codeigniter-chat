<?php

namespace App\Filters;

use App\Helpers\ChatHelper;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

/**
 * Applies a named validation rule group to the incoming request.
 *
 * Attach the filter with an argument such as `validate:message`.
 */
class ValidateInputFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $ruleGroup = $arguments[0] ?? null;

        if ($ruleGroup === null || $ruleGroup === '') {
            throw new InvalidArgumentException('The validate filter requires a validation rule group.');
        }

        $validation = service('validation')->reset();
        $validation->setRuleGroup($ruleGroup);

        if ($validation->withRequest($request)->run()) {
            return null;
        }

        $errors = $validation->getErrors();
        $message = $this->validationMessage($ruleGroup);

        log_message('warning', $message, ['errors' => $errors]);

        $format = $this->preferredFormat($request);

        if ($format === 'json') {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'type' => 'validation',
                    'message' => $message,
                    'errors' => $errors,
                ]);
        }

        if ($format === 'xml') {
            return service('response')
                ->setStatusCode(400)
                ->setHeader('Content-Type', 'application/xml; charset=UTF-8')
                ->setBody($this->xmlErrorResponse($message, $errors));
        }

        session()->setFlashdata('error', $message);
        session()->setFlashdata('errors', $errors);

        return redirect()->back()->withInput();
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }

    private function validationMessage(string $ruleGroup): string
    {
        return match ($ruleGroup) {
            'message' => 'Message validation failed',
            'registration' => 'Registration validation failed',
            'login' => 'Login validation failed',
            default => 'Validation failed',
        };
    }

    private function preferredFormat(RequestInterface $request): string
    {
        if ($request->isAJAX()) {
            return 'json';
        }

        if (! $request instanceof IncomingRequest) {
            return 'html';
        }

        return match ($request->negotiate('media', [
            'text/html',
            'application/json',
            'application/xml',
            'text/xml',
        ])) {
            'application/json' => 'json',
            'application/xml', 'text/xml' => 'xml',
            default => 'html',
        };
    }

    /**
     * @param array<string, string> $errors
     */
    private function xmlErrorResponse(string $message, array $errors): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<response><success>false</success><type>validation</type>';
        $xml .= '<message>' . ChatHelper::escapeForXml($message) . '</message><errors>';

        foreach ($errors as $field => $error) {
            $xml .= '<' . ChatHelper::escapeForXml($field) . '>' . ChatHelper::escapeForXml($error) . '</' . ChatHelper::escapeForXml($field) . '>';
        }

        return $xml . '</errors></response>';
    }
}
