# Contributing to CodeIgniter Chat

Thank you for your interest in contributing to this project! This is a learning project designed to demonstrate different frontend implementations with CodeIgniter 4.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Set up the development environment (see [docs/setup-guide.md](docs/setup-guide.md))
4. Create a new branch for your feature or fix

## Development Workflow

### Running the Application

```bash
# Start the PHP development server
php spark serve

# In a separate terminal, start Vite for frontend assets
npm run dev

# Optional: Start the WebSocket server for real-time features
php spark websocket:start
```

### Running Tests

Always run tests before submitting a pull request:

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit      # Unit tests only
composer test:feature   # Feature tests only
composer test:database  # Database tests only

# Generate coverage report (outputs to build/logs/html/)
composer test:coverage
```

### Code Quality

This project uses PHPStan for static analysis and PHP-CS-Fixer for code style:

```bash
# Run static analysis
composer analyse

# Check code style (no changes made)
composer cs-check

# Automatically fix code style issues
composer cs-fix
```

Please ensure your code passes both static analysis and code style checks before submitting a pull request.

## Code Style Guidelines

- Follow PSR-12 coding standards (enforced by PHP-CS-Fixer)
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and small

## Pull Request Process

1. Ensure all tests pass (`composer test`)
2. Run static analysis (`composer analyse`)
3. Fix any code style issues (`composer cs-fix`)
4. Update documentation if needed
5. Write a clear description of your changes

## Project Structure

```
codeigniter-chat/
├── app/                    # CodeIgniter application code
│   ├── Controllers/        # HTTP controllers
│   ├── Models/             # Database models
│   ├── Views/              # View templates
│   ├── Helpers/            # Helper functions
│   └── Libraries/          # Custom libraries
├── src/                    # Frontend source files
│   ├── js/                 # JavaScript (jQuery implementations)
│   ├── vue/                # Vue.js components
│   └── svelte/             # Svelte components
├── tests/                  # PHPUnit tests
│   ├── unit/               # Unit tests
│   ├── feature/            # Feature tests
│   └── database/           # Database tests
└── docs/                   # Documentation
```

## Questions?

If you have questions about contributing, feel free to open an issue for discussion.
