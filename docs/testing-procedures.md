# CodeIgniter Chat Testing Procedures and Requirements

This document outlines the testing procedures and requirements for the CodeIgniter Chat application. It provides guidelines for writing and running tests, as well as best practices for ensuring code quality.

## Testing Framework

CodeIgniter Chat uses PHPUnit for testing, which is integrated with CodeIgniter 4's testing framework. This provides a robust way to test your application with:

- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test the interaction between components
- **Database Tests**: Test database interactions

## Test Types

### Unit Tests

Unit tests focus on testing individual components (classes, methods, functions) in isolation. They should be:

- Fast to execute
- Independent of external systems (database, filesystem, network)
- Focused on a single unit of functionality

Unit tests are located in the `tests/unit/` directory.

### Feature Tests

Feature tests focus on testing the interaction between components and the overall behavior of the application. They:

- Test multiple components working together
- May interact with the HTTP layer
- Verify that features work as expected from an end-user perspective

Feature tests are located in the `tests/feature/` directory.

### Database Tests

Database tests focus on testing database interactions. They:

- Test models and database queries
- Verify that data is correctly stored, retrieved, updated, and deleted
- Use a test database to avoid affecting production data

Database tests are located in the `tests/database/` directory.

## Test Requirements

### Coverage Requirements

The CodeIgniter Chat project aims for high test coverage:

- **Minimum Coverage**: 80% overall code coverage
- **Target Coverage**: 90% for critical components
- **Uncovered Code**: Should be documented with a reason for exclusion

### Test Quality Requirements

Tests should be:

1. **Readable**: Clear and easy to understand
2. **Maintainable**: Easy to update when the code changes
3. **Reliable**: Produce consistent results
4. **Fast**: Execute quickly to enable frequent testing
5. **Independent**: Not dependent on other tests or external factors

## Setting Up the Testing Environment

### Prerequisites

- PHPUnit (installed via Composer)
- Test database (separate from development/production)

### Configuration

1. Configure the test database in `phpunit.xml.dist`:

```xml
<env name="database.tests.hostname" value="localhost"/>
<env name="database.tests.database" value="ci_chat_test"/>
<env name="database.tests.username" value="root"/>
<env name="database.tests.password" value=""/>
<env name="database.tests.DBDriver" value="MySQLi"/>
```

2. Ensure your test database exists:

```sql
CREATE DATABASE ci_chat_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Running Tests

### Using Composer Scripts

The project includes Composer scripts for running tests:

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only database tests
composer test:database

# Run only feature tests
composer test:feature

# Generate HTML coverage report
composer test:coverage
```

### Using PHPUnit Directly

You can also run PHPUnit directly:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific tests
./vendor/bin/phpunit tests/unit/TextHelperTest.php

# Run tests with specific group
./vendor/bin/phpunit --group database

# If you're using Windows
vendor\bin\phpunit
```

## Writing Tests

### Test File Structure

Test files should follow this structure:

```php
<?php

namespace Tests\Unit; // or Feature, Database

use CodeIgniter\Test\CIUnitTestCase; // or appropriate base class
use App\Models\YourModel; // import the class being tested

/**
 * @internal
 */
final class YourModelTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set up test dependencies
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        parent::tearDown();
    }

    public function testSomeMethod(): void
    {
        // Arrange
        $model = new YourModel();
        $expectedResult = 'expected value';

        // Act
        $actualResult = $model->someMethod();

        // Assert
        $this->assertEquals($expectedResult, $actualResult);
    }
}
```

### Unit Test Example

Here's an example of a unit test for a helper class:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\TextHelper;

/**
 * @internal
 */
final class TextHelperTest extends CIUnitTestCase
{
    public function testTruncate(): void
    {
        // Test with string shorter than the limit
        $shortString = 'This is a short string';
        $this->assertEquals(
            $shortString,
            TextHelper::truncate($shortString, 100),
            'Short strings should not be truncated'
        );

        // Test with string longer than the limit
        $longString = 'This is a very long string that should be truncated because it exceeds the limit';
        $expected = 'This is a very long string that should be truncated because ...';
        $this->assertEquals(
            $expected,
            TextHelper::truncate($longString, 60),
            'Long strings should be truncated to the specified length plus ellipsis'
        );
    }
}
```

### Feature Test Example

Here's an example of a feature test for a controller:

```php
<?php

namespace Tests\Feature;

use CodeIgniter\Test\FeatureTestCase;

/**
 * @internal
 */
final class ChatControllerTest extends FeatureTestCase
{
    public function testIndex(): void
    {
        $result = $this->get('chat');
        
        $result->assertStatus(200);
        $result->assertSee('Chat Room');
    }

    public function testSendMessage(): void
    {
        $result = $this->withSession(['user_id' => 1])
            ->post('chat/send', [
                'message' => 'Test message'
            ]);
        
        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);
    }
}
```

### Database Test Example

Here's an example of a database test for a model:

```php
<?php

namespace Tests\Database;

use CodeIgniter\Test\DatabaseTestCase;
use App\Models\ChatModel;

/**
 * @internal
 */
final class ChatModelTest extends DatabaseTestCase
{
    protected $refresh = true;
    protected $seed = 'TestSeeder';

    public function testGetMessages(): void
    {
        $model = new ChatModel();
        $messages = $model->getMessages(10);
        
        $this->assertIsArray($messages);
        $this->assertLessThanOrEqual(10, count($messages));
    }

    public function testSaveMessage(): void
    {
        $model = new ChatModel();
        $data = [
            'user' => 'testuser',
            'msg' => 'Test message',
            'time' => time()
        ];
        
        $result = $model->saveMessage($data);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Verify the message was saved
        $message = $model->find($result);
        $this->assertEquals($data['user'], $message['user']);
        $this->assertEquals($data['msg'], $message['msg']);
    }
}
```

## Mocking

### Mocking Dependencies

Use mocking to isolate the code being tested from its dependencies:

```php
<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ChatService;
use App\Models\ChatModel;

/**
 * @internal
 */
final class ChatServiceTest extends CIUnitTestCase
{
    public function testGetRecentMessages(): void
    {
        // Create a mock of the ChatModel
        $mockModel = $this->createMock(ChatModel::class);
        
        // Configure the mock to return a specific result
        $mockModel->method('getMessages')
            ->willReturn([
                ['id' => 1, 'user' => 'user1', 'msg' => 'Hello', 'time' => time()],
                ['id' => 2, 'user' => 'user2', 'msg' => 'Hi', 'time' => time()]
            ]);
        
        // Inject the mock into the service
        $service = new ChatService($mockModel);
        
        // Test the service method
        $messages = $service->getRecentMessages(5);
        
        // Assert the result
        $this->assertCount(2, $messages);
    }
}
```

## Test-Driven Development (TDD)

The CodeIgniter Chat project encourages Test-Driven Development:

1. **Write a failing test** that defines the expected behavior
2. **Write the minimum code** needed to make the test pass
3. **Refactor the code** to improve its design while keeping the tests passing

## Continuous Integration

The project uses GitHub Actions for continuous integration:

- Tests are run automatically on each push and pull request
- Code coverage reports are generated
- Pull requests with failing tests are not merged

## Testing Best Practices

1. **Test each method in isolation**
   - Focus on testing one method at a time
   - Mock dependencies to isolate the method being tested

2. **Create both positive and negative tests**
   - Test that methods work correctly with valid inputs
   - Test that methods handle invalid inputs appropriately

3. **Test edge cases and boundary conditions**
   - Test with empty inputs, null values, etc.
   - Test with minimum and maximum values

4. **Keep tests simple and focused**
   - Each test should verify one specific behavior
   - Avoid complex test logic that could introduce its own bugs

5. **Use descriptive test names**
   - Test names should describe what is being tested
   - Example: `testUserCannotLoginWithInvalidPassword()`

6. **Use data providers for multiple test cases**
   - Use PHPUnit's `@dataProvider` annotation to test multiple inputs
   - Example:
     ```php
     /**
      * @dataProvider validEmailProvider
      */
     public function testValidEmails(string $email): void
     {
         $this->assertTrue(is_valid_email($email));
     }
     
     public function validEmailProvider(): array
     {
         return [
             ['user@example.com'],
             ['user.name@example.com'],
             ['user+tag@example.com']
         ];
     }
     ```

7. **Clean up after tests**
   - Use `setUp()` and `tearDown()` methods to set up and clean up test data
   - Ensure tests don't leave behind data that could affect other tests

## Troubleshooting Common Testing Issues

1. **Tests are slow**
   - Use unit tests instead of feature or database tests when possible
   - Minimize database operations in tests
   - Use in-memory databases for testing

2. **Tests are flaky (sometimes pass, sometimes fail)**
   - Ensure tests are independent of each other
   - Avoid dependencies on external services
   - Check for race conditions in asynchronous code

3. **Database tests fail**
   - Ensure the test database is properly configured
   - Check that migrations are being run before tests
   - Verify that the database is being refreshed between tests

4. **Mock objects don't work as expected**
   - Ensure you're mocking the correct methods
   - Check that the mock is being injected correctly
   - Verify that the code is using the mock instead of the real object

## Additional Resources

- [CodeIgniter 4 Testing Documentation](https://codeigniter.com/user_guide/testing/index.html)
- [PHPUnit Documentation](https://phpunit.readthedocs.io/)
- [Test-Driven Development by Example](https://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530) by Kent Beck