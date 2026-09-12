<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    /**
     * Rules for posting a chat message.
     *
     * @var array<string, array{rules: string, errors: array<string, string>}>
     */
    public array $message = [
        'message' => [
            'rules' => 'required|min_length[1]|max_length[500]',
            'errors' => [
                'required' => 'Message is required',
                'min_length' => 'Message must be at least 1 character long',
                'max_length' => 'Message cannot exceed 500 characters',
            ],
        ],
    ];

    /**
     * Rules for registering a user.
     *
     * @var array<string, array{rules: string, errors: array<string, string>}>
     */
    public array $registration = [
        'username' => [
            'rules' => 'required|min_length[3]|max_length[50]|alpha_numeric|is_unique[users.username]',
            'errors' => [
                'required' => 'Username is required',
                'min_length' => 'Username must be at least 3 characters long',
                'max_length' => 'Username cannot exceed 50 characters',
                'alpha_numeric' => 'Username can only contain alphanumeric characters',
                'is_unique' => 'Username is already taken',
            ],
        ],
        'email' => [
            'rules' => 'required|valid_email|is_unique[users.email]',
            'errors' => [
                'required' => 'Email is required',
                'valid_email' => 'Please enter a valid email address',
                'is_unique' => 'Email is already registered',
            ],
        ],
        'password' => [
            'rules' => 'required|min_length[8]',
            'errors' => [
                'required' => 'Password is required',
                'min_length' => 'Password must be at least 8 characters long',
            ],
        ],
        'password_confirm' => [
            'rules' => 'required|matches[password]',
            'errors' => [
                'required' => 'Password confirmation is required',
                'matches' => 'Passwords do not match',
            ],
        ],
    ];

    /**
     * Rules for authenticating a user.
     *
     * @var array<string, array{rules: string, errors: array<string, string>}>
     */
    public array $login = [
        'username' => [
            'rules' => 'required',
            'errors' => [
                'required' => 'Username is required',
            ],
        ],
        'password' => [
            'rules' => 'required',
            'errors' => [
                'required' => 'Password is required',
            ],
        ],
    ];
}
