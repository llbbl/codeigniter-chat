# Contributing to CodeIgniter Chat

Thank you for considering contributing to the CodeIgniter Chat project! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

By participating in this project, you agree to abide by the following code of conduct:

- Be respectful and inclusive of all contributors regardless of background or experience level
- Provide constructive feedback and be open to receiving feedback
- Focus on the best possible outcome for the project and its users
- Be patient and understanding with other contributors

## How to Contribute

There are many ways to contribute to the CodeIgniter Chat project:

1. **Reporting Bugs**: Help improve the project by reporting bugs or issues
2. **Suggesting Enhancements**: Propose new features or improvements
3. **Writing Code**: Submit pull requests with bug fixes or new features
4. **Improving Documentation**: Help make the documentation more comprehensive and clear
5. **Reviewing Pull Requests**: Help review and test other contributors' code

## Getting Started

Before you begin contributing, please:

1. **Set up your development environment** following the instructions in [setup-guide.md](setup-guide.md)
2. **Familiarize yourself with the codebase** by reading the documentation and exploring the code
3. **Check the issue tracker** to see if your bug report or feature request has already been reported

## Reporting Bugs

When reporting bugs, please include:

1. **A clear and descriptive title**
2. **Detailed steps to reproduce the bug**
3. **Expected behavior**
4. **Actual behavior**
5. **Screenshots** (if applicable)
6. **Environment information**:
   - PHP version
   - MySQL version
   - Browser and version
   - Operating system

## Suggesting Enhancements

When suggesting enhancements, please include:

1. **A clear and descriptive title**
2. **A detailed description of the proposed enhancement**
3. **Justification for why this enhancement would be valuable**
4. **Examples of how the enhancement would be used**
5. **Any relevant references or examples from other projects**

## Pull Request Process

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies** using Composer and NPM
3. **Make your changes** following the coding standards
4. **Add or update tests** as necessary
5. **Ensure all tests pass** by running `composer test`
6. **Update documentation** to reflect any changes
7. **Submit a pull request** with a clear description of the changes

### Branch Naming Convention

- Use `feature/` prefix for new features
- Use `bugfix/` prefix for bug fixes
- Use `docs/` prefix for documentation changes
- Use `refactor/` prefix for code refactoring
- Use `test/` prefix for adding or updating tests

Example: `feature/websocket-implementation` or `bugfix/message-display-issue`

### Commit Message Guidelines

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters or less
- Reference issues and pull requests liberally after the first line
- Consider starting the commit message with an applicable emoji:
  - 🎨 `:art:` when improving the format/structure of the code
  - 🐛 `:bug:` when fixing a bug
  - 📝 `:memo:` when adding or updating documentation
  - ✨ `:sparkles:` when adding a new feature
  - ⚡️ `:zap:` when improving performance
  - 🔒 `:lock:` when dealing with security

## Coding Standards

### PHP Code Style

Follow the CodeIgniter 4 style guide:

- Use spaces for indentation (4 spaces per level)
- Class names should use PascalCase (e.g., `MyClass`)
- Method and variable names should use camelCase (e.g., `myMethod`)
- Constants should be UPPERCASE (e.g., `MY_CONSTANT`)

### Documentation

- Use PHPDoc comments for classes, methods, and properties
- Include a brief description, parameter types, and return types
- Example:
  ```php
  /**
   * Truncates a string to the specified length
   *
   * @param string $string The string to truncate
   * @param int $length The maximum length of the string
   * @param string $suffix The suffix to add to truncated strings
   * @return string The truncated string
   */
  public function truncate(string $string, int $length, string $suffix = '...'): string
  {
      // Method implementation
  }
  ```

### JavaScript Code Style

- Use 2 spaces for indentation
- Use semicolons at the end of statements
- Use camelCase for variable and function names
- Use ES6 features when appropriate
- Document functions with JSDoc comments

### CSS/SASS Code Style

- Use 2 spaces for indentation
- Use kebab-case for class names (e.g., `my-class`)
- Group related properties together
- Add comments for complex selectors or rules

## Testing

- Write tests for all new features and bug fixes
- Ensure all tests pass before submitting a pull request
- Follow the testing guidelines in [testing-procedures.md](testing-procedures.md)

## Review Process

1. At least one core team member must review and approve your pull request
2. Automated tests must pass
3. All review comments must be addressed
4. Once approved, a core team member will merge your pull request

## Development Workflow

1. Create a feature branch from the main branch
2. Implement your changes
3. Write tests for your changes
4. Run existing tests to ensure you haven't broken anything
5. Submit a pull request

## License

By contributing to CodeIgniter Chat, you agree that your contributions will be licensed under the project's license.

## Questions?

If you have any questions about contributing, please open an issue with the label "question" or contact one of the core team members.

Thank you for contributing to CodeIgniter Chat!