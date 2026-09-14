<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PwaAssetsTest extends CIUnitTestCase
{
    public function testManifestHasInstallableApplicationMetadata(): void
    {
        $manifest = json_decode(
            file_get_contents(FCPATH . 'manifest.webmanifest'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(FCPATH . ltrim($icon['src'], '/'));
        }
    }

    public function testBothFrameworkViewsExposeTheManifest(): void
    {
        foreach (['vueView.php', 'svelteView.php'] as $view) {
            $contents = file_get_contents(APPPATH . 'Views/chat/' . $view);
            $this->assertStringContainsString('rel="manifest" href="/manifest.webmanifest"', $contents);
            $this->assertStringContainsString('name="theme-color"', $contents);
        }
    }

    public function testOfflineFallbackDoesNotContainAuthenticatedData(): void
    {
        $contents = file_get_contents(FCPATH . 'offline.html');

        $this->assertStringContainsString('You’re offline', $contents);
        $this->assertStringNotContainsString('CURRENT_USER', $contents);
        $this->assertStringNotContainsString('WEBSOCKET_TOKEN', $contents);
    }
}
