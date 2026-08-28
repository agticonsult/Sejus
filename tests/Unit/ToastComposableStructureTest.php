<?php

namespace Tests\Unit;

use Tests\TestCase;

class ToastComposableStructureTest extends TestCase
{
    /**
     * Test useToast.js file presence and exported functions.
     */
    public function test_use_toast_js_structure_and_exports(): void
    {
        $toastFilePath = resource_path('js/Composables/useToast.js');
        $this->assertFileExists($toastFilePath, 'useToast.js composable must exist');

        $content = file_get_contents($toastFilePath);
        $this->assertStringContainsString('success', $content);
        $this->assertStringContainsString('error', $content);
        $this->assertStringContainsString('warning', $content);
        $this->assertStringContainsString('info', $content);
    }

    /**
     * Test ToastContainer.vue file presence and CSS styling.
     */
    public function test_toast_container_vue_presence(): void
    {
        $containerPath = resource_path('js/Components/ToastContainer.vue');
        $this->assertFileExists($containerPath, 'ToastContainer.vue component must exist');

        $content = file_get_contents($containerPath);
        $this->assertStringContainsString('fixed', $content);
    }
}
