<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\FileHelper;
use org\bovigo\vfs\vfsStream;

/**
 * @internal
 */
final class FileHelperTest extends CIUnitTestCase
{
    private $root;
    private $testFilePath;
    private $testDirPath;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up virtual filesystem
        $this->root = vfsStream::setup('root');
        $this->testFilePath = vfsStream::url('root/test.txt');
        $this->testDirPath = vfsStream::url('root/testdir');
        
        // Create a test file
        file_put_contents($this->testFilePath, 'Test content');
    }
    
    public function testGetExtension(): void
    {
        $this->assertEquals(
            'txt',
            FileHelper::getExtension($this->testFilePath),
            'Should return the correct file extension'
        );
        
        $this->assertEquals(
            'php',
            FileHelper::getExtension('test.php'),
            'Should return the correct file extension'
        );
        
        $this->assertEquals(
            '',
            FileHelper::getExtension('test'),
            'Should return empty string for files without extension'
        );
    }
    
    public function testGetFilename(): void
    {
        $this->assertEquals(
            'test',
            FileHelper::getFilename($this->testFilePath),
            'Should return the filename without extension'
        );
        
        $this->assertEquals(
            'test',
            FileHelper::getFilename('test.php'),
            'Should return the filename without extension'
        );
        
        $this->assertEquals(
            'test',
            FileHelper::getFilename('test'),
            'Should return the filename for files without extension'
        );
    }
    
    public function testExists(): void
    {
        $this->assertTrue(
            FileHelper::exists($this->testFilePath),
            'Should return true for existing files'
        );
        
        $this->assertFalse(
            FileHelper::exists(vfsStream::url('root/nonexistent.txt')),
            'Should return false for non-existent files'
        );
    }
    
    public function testCreateDirectory(): void
    {
        $this->assertTrue(
            FileHelper::createDirectory($this->testDirPath),
            'Should create directory and return true'
        );
        
        $this->assertTrue(
            is_dir($this->testDirPath),
            'Directory should exist after creation'
        );
        
        // Test creating an existing directory
        $this->assertTrue(
            FileHelper::createDirectory($this->testDirPath),
            'Should return true for existing directories'
        );
    }
    
    public function testDeleteFile(): void
    {
        $this->assertTrue(
            FileHelper::deleteFile($this->testFilePath),
            'Should delete file and return true'
        );
        
        $this->assertFalse(
            file_exists($this->testFilePath),
            'File should not exist after deletion'
        );
        
        // Test deleting a non-existent file
        $this->assertFalse(
            FileHelper::deleteFile($this->testFilePath),
            'Should return false for non-existent files'
        );
    }
    
    public function testReadFile(): void
    {
        // Create a new test file since the previous one was deleted
        file_put_contents($this->testFilePath, 'Test content');
        
        $this->assertEquals(
            'Test content',
            FileHelper::readFile($this->testFilePath),
            'Should read file contents correctly'
        );
        
        // Test reading a non-existent file
        $this->assertFalse(
            FileHelper::readFile(vfsStream::url('root/nonexistent.txt')),
            'Should return false for non-existent files'
        );
    }
    
    public function testWriteFile(): void
    {
        $newFilePath = vfsStream::url('root/new.txt');
        $content = 'New content';
        
        $this->assertTrue(
            FileHelper::writeFile($newFilePath, $content),
            'Should write file and return true'
        );
        
        $this->assertTrue(
            file_exists($newFilePath),
            'File should exist after writing'
        );
        
        $this->assertEquals(
            $content,
            file_get_contents($newFilePath),
            'File should contain the written content'
        );
        
        // Test appending to a file
        $appendContent = ' - Appended';
        $this->assertTrue(
            FileHelper::writeFile($newFilePath, $appendContent, true),
            'Should append to file and return true'
        );
        
        $this->assertEquals(
            $content . $appendContent,
            file_get_contents($newFilePath),
            'File should contain the original and appended content'
        );
    }
    
    public function testGetFiles(): void
    {
        // glob() doesn't work with vfsStream, so use a real temp directory
        $realTempDir = sys_get_temp_dir() . '/filehelper_test_' . uniqid();
        mkdir($realTempDir, 0755, true);

        try {
            file_put_contents($realTempDir . '/file1.txt', 'Content 1');
            file_put_contents($realTempDir . '/file2.txt', 'Content 2');
            file_put_contents($realTempDir . '/file3.php', 'Content 3');

            $files = FileHelper::getFiles($realTempDir);

            $this->assertIsArray(
                $files,
                'Should return an array'
            );

            $this->assertCount(
                3,
                $files,
                'Should return all files in the directory'
            );

            // Test with pattern
            $txtFiles = FileHelper::getFiles($realTempDir, '*.txt');

            $this->assertCount(
                2,
                $txtFiles,
                'Should return only files matching the pattern'
            );

            // Test with non-existent directory
            $this->assertEmpty(
                FileHelper::getFiles($realTempDir . '/nonexistent'),
                'Should return empty array for non-existent directories'
            );
        } finally {
            // Cleanup
            @unlink($realTempDir . '/file1.txt');
            @unlink($realTempDir . '/file2.txt');
            @unlink($realTempDir . '/file3.php');
            @rmdir($realTempDir);
        }
    }
    
    public function testFormatSize(): void
    {
        $this->assertEquals(
            '1.00 KB',
            FileHelper::formatSize(1024),
            'Should format 1024 bytes as 1.00 KB'
        );
        
        $this->assertEquals(
            '1.00 MB',
            FileHelper::formatSize(1048576),
            'Should format 1048576 bytes as 1.00 MB'
        );
        
        $this->assertEquals(
            '1.50 KB',
            FileHelper::formatSize(1536),
            'Should format 1536 bytes as 1.50 KB'
        );
        
        $this->assertEquals(
            '100.00 B',
            FileHelper::formatSize(100),
            'Should format 100 bytes as 100.00 B'
        );
    }
}