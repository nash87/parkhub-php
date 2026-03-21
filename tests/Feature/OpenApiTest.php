<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_l5_swagger_config_exists(): void
    {
        $this->assertFileExists(config_path('l5-swagger.php'));
    }

    public function test_openapi_info_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\OpenApi\Info::class));
    }
}
