<?php

namespace Tests\Unit;

use App\Services\ItTools\ItToolStatusService;
use Tests\TestCase;

class ItToolsServicesTest extends TestCase
{
    public function test_expiry_status_levels_are_stable(): void
    {
        $this->assertSame('expired', ItToolStatusService::level(-1));
        $this->assertSame('critical', ItToolStatusService::level(7));
        $this->assertSame('warning', ItToolStatusService::level(30));
        $this->assertSame('notice', ItToolStatusService::level(60));
        $this->assertSame('ok', ItToolStatusService::level(61));
        $this->assertSame('unknown', ItToolStatusService::level(null));
    }

    public function test_it_tool_status_summary_flags_action_required(): void
    {
        $summary = ItToolStatusService::summary(3, 'ssl');
        $this->assertSame('ssl', $summary['type']);
        $this->assertSame('critical', $summary['level']);
        $this->assertTrue($summary['action_required']);
    }
}
