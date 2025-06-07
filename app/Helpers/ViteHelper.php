<?php

namespace App\Helpers;

/**
 * ViteHelper
 * 
 * Helper class for loading assets built with Vite
 */
class ViteHelper
{
    /**
     * The Vite manifest file path
     * 
     * @var string
     */
    private static $manifestPath = ROOTPATH . 'public/dist/manifest.json';

    /**
     * The Vite manifest data
     * 
     * @var array|null
     */
    private static $manifest = null;

    /**
     * The Vite development server URL
     * 
     * @var string
     */
    private static $devServerUrl = 'http://localhost:5173';

    /**
     * Check if we're in development mode
     * 
     * @return bool
     */
    public static function isDev(): bool
    {
        return ENVIRONMENT === 'development' && !file_exists(self::$manifestPath);
    }

    /**
     * Get the Vite manifest
     * 
     * @return array
     */
    public static function getManifest(): array
    {
        if (self::$manifest === null) {
            if (file_exists(self::$manifestPath)) {
                self::$manifest = json_decode(file_get_contents(self::$manifestPath), true);
            } else {
                self::$manifest = [];
            }
        }

        return self::$manifest;
    }

    /**
     * Get the asset URL
     * 
     * @param string $path The asset path
     * @return string The asset URL
     */
    public static function asset(string $path): string
    {
        // In development mode, use the Vite dev server
        if (self::isDev()) {
            return self::$devServerUrl . '/' . $path;
        }

        // In production mode, use the manifest to get the correct file
        $manifest = self::getManifest();
        
        if (isset($manifest[$path])) {
            return base_url('dist/' . $manifest[$path]['file']);
        }

        // Fallback to the original path if not found in manifest
        return base_url('dist/' . $path);
    }

    /**
     * Get the CSS tags for a JavaScript entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The CSS tags
     */
    public static function cssTag(string $entry): string
    {
        // In development mode, CSS is injected by Vite
        if (self::isDev()) {
            return '';
        }

        $manifest = self::getManifest();
        
        if (!isset($manifest[$entry])) {
            return '';
        }

        $tags = '';
        
        // Check if the entry has CSS imports
        if (isset($manifest[$entry]['css']) && is_array($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $css) {
                $tags .= '<link rel="stylesheet" href="' . base_url('dist/' . $css) . '">';
            }
        }

        return $tags;
    }

    /**
     * Get the JavaScript tag for an entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The JavaScript tag
     */
    public static function jsTag(string $entry): string
    {
        $url = self::asset($entry);
        
        return '<script type="module" src="' . $url . '"></script>';
    }

    /**
     * Get both CSS and JavaScript tags for an entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The tags
     */
    public static function tags(string $entry): string
    {
        return self::cssTag($entry) . self::jsTag($entry);
    }
}