<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use PHPUnit\Framework\TestCase;

class SettingTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $setting = new Setting();
        $fillable = $setting->getFillable();

        $this->assertContains('key', $fillable);
        $this->assertContains('value', $fillable);
    }
}
