<?php

namespace App\Helpers;

use CodeIgniter\I18n\Time;

/**
 * DateTime Helper
 * 
 * Contains utility functions for date and time operations
 */
class DateTimeHelper
{
    /**
     * Format a timestamp to a human-readable date
     * 
     * @param int|string $timestamp The timestamp to format
     * @param string $format The date format (default: 'Y-m-d H:i:s')
     * @return string The formatted date
     */
    public static function formatDate(int|string $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        return Time::createFromTimestamp($timestamp)->format($format);
    }
    
    /**
     * Get a human-readable time difference (e.g., "2 hours ago")
     * 
     * @param int|string $timestamp The timestamp to compare
     * @return string The human-readable time difference
     */
    public static function timeAgo(int|string $timestamp): string
    {
        return Time::createFromTimestamp($timestamp)->humanize();
    }
    
    /**
     * Get the current timestamp
     * 
     * @return int The current timestamp
     */
    public static function now(): int
    {
        return Time::now()->getTimestamp();
    }
    
    /**
     * Check if a timestamp is in the future
     * 
     * @param int|string $timestamp The timestamp to check
     * @return bool True if the timestamp is in the future
     */
    public static function isFuture(int|string $timestamp): bool
    {
        return Time::createFromTimestamp($timestamp)->isAfter(Time::now());
    }
    
    /**
     * Check if a timestamp is in the past
     * 
     * @param int|string $timestamp The timestamp to check
     * @return bool True if the timestamp is in the past
     */
    public static function isPast(int|string $timestamp): bool
    {
        return Time::createFromTimestamp($timestamp)->isBefore(Time::now());
    }
    
    /**
     * Add a time interval to a timestamp
     * 
     * @param int|string $timestamp The base timestamp
     * @param string $interval The interval to add (e.g., '1 day', '2 hours')
     * @return int The new timestamp
     */
    public static function addTime(int|string $timestamp, string $interval): int
    {
        return Time::createFromTimestamp($timestamp)->modify('+' . $interval)->getTimestamp();
    }
    
    /**
     * Subtract a time interval from a timestamp
     * 
     * @param int|string $timestamp The base timestamp
     * @param string $interval The interval to subtract (e.g., '1 day', '2 hours')
     * @return int The new timestamp
     */
    public static function subtractTime(int|string $timestamp, string $interval): int
    {
        return Time::createFromTimestamp($timestamp)->modify('-' . $interval)->getTimestamp();
    }
}