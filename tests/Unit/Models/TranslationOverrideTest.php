<?php

namespace Tests\Unit\Models;

use App\Models\TranslationOverride;
use PHPUnit\Framework\TestCase;

class TranslationOverrideTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $override = new TranslationOverride();
        $fillable = $override->getFillable();

        $this->assertContains('language', $fillable);
        $this->assertContains('key', $fillable);
        $this->assertContains('value', $fillable);
    }
}
