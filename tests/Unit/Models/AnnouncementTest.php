<?php

namespace Tests\Unit\Models;

use App\Models\Announcement;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $announcement = new Announcement;
        $fillable = $announcement->getFillable();

        $this->assertContains('title', $fillable);
        $this->assertContains('message', $fillable);
        $this->assertContains('severity', $fillable);
        $this->assertContains('active', $fillable);
        $this->assertContains('created_by', $fillable);
        $this->assertContains('expires_at', $fillable);
    }
}
