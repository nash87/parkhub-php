<?php

namespace Tests\Unit\Models;

use App\Models\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $webhook = new Webhook();
        $fillable = $webhook->getFillable();

        $this->assertContains('url', $fillable);
        $this->assertContains('events', $fillable);
        $this->assertContains('secret', $fillable);
        $this->assertContains('active', $fillable);
    }
}
