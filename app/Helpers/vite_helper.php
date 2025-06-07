<?php

/**
 * Vite Helper Functions
 * 
 * This file contains helper functions for working with Vite assets
 */

if (!function_exists('vite_asset')) {
    /**
     * Get the URL for a Vite asset
     * 
     * @param string $path The asset path
     * @return string The asset URL
     */
    function vite_asset(string $path): string
    {
        return \App\Helpers\ViteHelper::asset($path);
    }
}

if (!function_exists('vite_js_tag')) {
    /**
     * Get the JavaScript tag for a Vite entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The JavaScript tag
     */
    function vite_js_tag(string $entry): string
    {
        return \App\Helpers\ViteHelper::jsTag($entry);
    }
}

if (!function_exists('vite_css_tag')) {
    /**
     * Get the CSS tags for a Vite entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The CSS tags
     */
    function vite_css_tag(string $entry): string
    {
        return \App\Helpers\ViteHelper::cssTag($entry);
    }
}

if (!function_exists('vite_tags')) {
    /**
     * Get both CSS and JavaScript tags for a Vite entry
     * 
     * @param string $entry The JavaScript entry path
     * @return string The tags
     */
    function vite_tags(string $entry): string
    {
        return \App\Helpers\ViteHelper::tags($entry);
    }
}