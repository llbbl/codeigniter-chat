<?php

namespace App\Helpers;

use CodeIgniter\Files\File;
use Config\Services;

/**
 * File Helper
 * 
 * Contains utility functions for file operations
 */
class FileHelper
{
    /**
     * Get file extension
     * 
     * @param string $filename The filename
     * @return string The file extension
     */
    public static function getExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }
    
    /**
     * Get file name without extension
     * 
     * @param string $filename The filename
     * @return string The file name without extension
     */
    public static function getFilename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }
    
    /**
     * Get file MIME type
     * 
     * @param string $path The file path
     * @return string The MIME type
     */
    public static function getMimeType(string $path): string
    {
        $file = new File($path);
        return $file->getMimeType();
    }
    
    /**
     * Get file size in bytes
     * 
     * @param string $path The file path
     * @return int The file size in bytes
     */
    public static function getSize(string $path): int
    {
        $file = new File($path);
        return $file->getSize();
    }
    
    /**
     * Format file size to human-readable format
     * 
     * @param int $bytes The file size in bytes
     * @param int $decimals The number of decimal places
     * @return string The formatted file size
     */
    public static function formatSize(int $bytes, int $decimals = 2): string
    {
        $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }
    
    /**
     * Check if a file exists
     * 
     * @param string $path The file path
     * @return bool True if the file exists
     */
    public static function exists(string $path): bool
    {
        return file_exists($path) && is_file($path);
    }
    
    /**
     * Create a directory if it doesn't exist
     * 
     * @param string $path The directory path
     * @param int $permissions The directory permissions
     * @param bool $recursive Whether to create parent directories
     * @return bool True if the directory was created or already exists
     */
    public static function createDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }
        
        return mkdir($path, $permissions, $recursive);
    }
    
    /**
     * Delete a file
     * 
     * @param string $path The file path
     * @return bool True if the file was deleted
     */
    public static function deleteFile(string $path): bool
    {
        if (!self::exists($path)) {
            return false;
        }
        
        return unlink($path);
    }
    
    /**
     * Read a file's contents
     * 
     * @param string $path The file path
     * @return string|false The file contents or false on failure
     */
    public static function readFile(string $path): string|false
    {
        if (!self::exists($path)) {
            return false;
        }
        
        return file_get_contents($path);
    }
    
    /**
     * Write contents to a file
     * 
     * @param string $path The file path
     * @param string $data The data to write
     * @param bool $append Whether to append to the file
     * @return bool True if the file was written
     */
    public static function writeFile(string $path, string $data, bool $append = false): bool
    {
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            self::createDirectory($directory);
        }
        
        $flag = $append ? FILE_APPEND : 0;
        
        return file_put_contents($path, $data, $flag) !== false;
    }
    
    /**
     * Get a list of files in a directory
     * 
     * @param string $directory The directory path
     * @param string $pattern The file pattern to match
     * @return array The list of files
     */
    public static function getFiles(string $directory, string $pattern = '*'): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        
        return glob(rtrim($directory, '/') . '/' . $pattern);
    }
}