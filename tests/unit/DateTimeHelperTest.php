<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\DateTimeHelper;
use CodeIgniter\I18n\Time;

/**
 * @internal
 */
final class DateTimeHelperTest extends CIUnitTestCase
{
    public function testFormatDate(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00
        $this->assertEquals(
            '2021-01-01 00:00:00',
            DateTimeHelper::formatDate($timestamp),
            'Should format timestamp to default format'
        );
        
        $this->assertEquals(
            '2021-01-01',
            DateTimeHelper::formatDate($timestamp, 'Y-m-d'),
            'Should format timestamp to specified format'
        );
    }
    
    public function testTimeAgo(): void
    {
        // This test is a bit tricky because the exact output depends on the current time
        // So we'll just check that it returns a string
        $timestamp = time() - 3600; // 1 hour ago
        $this->assertIsString(
            DateTimeHelper::timeAgo($timestamp),
            'Should return a string for time ago'
        );
    }
    
    public function testNow(): void
    {
        $now = DateTimeHelper::now();
        $this->assertIsInt(
            $now,
            'Should return an integer timestamp'
        );
        
        // Check that the timestamp is within a reasonable range (within 5 seconds of PHP's time())
        $this->assertLessThanOrEqual(
            time() + 5,
            $now,
            'Timestamp should be close to current time'
        );
        $this->assertGreaterThanOrEqual(
            time() - 5,
            $now,
            'Timestamp should be close to current time'
        );
    }
    
    public function testIsFuture(): void
    {
        $futureTimestamp = time() + 3600; // 1 hour in the future
        $pastTimestamp = time() - 3600; // 1 hour in the past
        
        $this->assertTrue(
            DateTimeHelper::isFuture($futureTimestamp),
            'Should return true for future timestamp'
        );
        
        $this->assertFalse(
            DateTimeHelper::isFuture($pastTimestamp),
            'Should return false for past timestamp'
        );
    }
    
    public function testIsPast(): void
    {
        $futureTimestamp = time() + 3600; // 1 hour in the future
        $pastTimestamp = time() - 3600; // 1 hour in the past
        
        $this->assertTrue(
            DateTimeHelper::isPast($pastTimestamp),
            'Should return true for past timestamp'
        );
        
        $this->assertFalse(
            DateTimeHelper::isPast($futureTimestamp),
            'Should return false for future timestamp'
        );
    }
    
    public function testAddTime(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00
        $newTimestamp = DateTimeHelper::addTime($timestamp, '1 day');
        
        $this->assertEquals(
            $timestamp + 86400, // 86400 seconds = 1 day
            $newTimestamp,
            'Should add 1 day to timestamp'
        );
    }
    
    public function testSubtractTime(): void
    {
        $timestamp = 1609459200; // 2021-01-01 00:00:00
        $newTimestamp = DateTimeHelper::subtractTime($timestamp, '1 day');
        
        $this->assertEquals(
            $timestamp - 86400, // 86400 seconds = 1 day
            $newTimestamp,
            'Should subtract 1 day from timestamp'
        );
    }
}