<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $log = new AuditLog;
        $fillable = $log->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('username', $fillable);
        $this->assertContains('action', $fillable);
        $this->assertContains('details', $fillable);
        $this->assertContains('ip_address', $fillable);
    }

    public function test_table_name(): void
    {
        $log = new AuditLog;
        $this->assertEquals('audit_log', $log->getTable());
    }
}
