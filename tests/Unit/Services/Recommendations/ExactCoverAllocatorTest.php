<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Recommendations;

use App\Services\Recommendations\ExactCoverAllocator;
use Tests\TestCase;

class ExactCoverAllocatorTest extends TestCase
{
    public function test_exact_cover_v1_solves_batch_constraints(): void
    {
        $result = (new ExactCoverAllocator)->solve(
            ['tenant:alpha', 'tenant:beta', 'ev', 'accessible'],
            [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha', 'ev'], 'weight' => 90],
                ['id' => 'slot-b', 'covers' => ['tenant:beta', 'accessible'], 'weight' => 80],
                ['id' => 'slot-c', 'covers' => ['tenant:beta'], 'weight' => 70],
            ]
        );

        $this->assertSame('solved', $result['status']);
        $this->assertSame(['slot-a', 'slot-b'], $result['selected_option_ids']);
        $this->assertSame(
            ['accessible', 'ev', 'tenant:alpha', 'tenant:beta'],
            $result['covered_constraints']
        );
    }

    public function test_exact_cover_v1_uses_deterministic_weight_and_id_tiebreaks(): void
    {
        $result = (new ExactCoverAllocator)->solve(
            ['tenant:alpha'],
            [
                ['id' => 'slot-b', 'covers' => ['tenant:alpha'], 'weight' => 80],
                ['id' => 'slot-a', 'covers' => ['tenant:alpha'], 'weight' => 80],
                ['id' => 'slot-c', 'covers' => ['tenant:alpha'], 'weight' => 70],
            ]
        );

        $this->assertSame('solved', $result['status']);
        $this->assertSame(['slot-a'], $result['selected_option_ids']);
    }

    public function test_exact_cover_v1_reports_no_solution_for_maintenance_gap(): void
    {
        $result = (new ExactCoverAllocator)->solve(
            ['tenant:alpha', 'maintenance:open'],
            [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha'], 'weight' => 90],
            ]
        );

        $this->assertSame('fallback_no_solution', $result['status']);
        $this->assertSame([], $result['selected_option_ids']);
    }

    public function test_exact_cover_v1_enforces_input_limits(): void
    {
        $result = (new ExactCoverAllocator)->solve(
            ['tenant:alpha'],
            [
                ['id' => 'slot-a', 'covers' => ['tenant:alpha'], 'weight' => 90],
                ['id' => 'slot-b', 'covers' => ['tenant:alpha'], 'weight' => 80],
            ],
            maxOptions: 1,
            maxSearchNodes: 10
        );

        $this->assertSame('fallback_input_limited', $result['status']);
        $this->assertSame(0, $result['search_nodes']);
    }

    public function test_exact_cover_v1_shared_fixtures_match_contract(): void
    {
        $fixtureNames = [
            'exact_cover_v1.batch_basic.json',
            'exact_cover_v1.empty.json',
            'exact_cover_v1.fairness_tiebreak.json',
            'exact_cover_v1.no_solution.json',
        ];
        $allocator = new ExactCoverAllocator;

        foreach ($fixtureNames as $fixtureName) {
            $fixturePath = base_path("docs/recommendation-engine-fixtures/{$fixtureName}");
            $fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);

            $result = $allocator->solve(
                $fixture['required_constraints'],
                $fixture['options']
            );

            $this->assertSame($fixture['expected']['status'], $result['status'], $fixtureName);
            $this->assertSame(
                $fixture['expected']['selected_option_ids'],
                $result['selected_option_ids'],
                $fixtureName
            );
            $this->assertSame(
                $fixture['expected']['covered_constraints'],
                $result['covered_constraints'],
                $fixtureName
            );
            $this->assertTrue($fixture['legal_boundary']['legal_review_required'], $fixtureName);
            $this->assertSame(
                'required_before_customer_wording',
                $fixture['legal_boundary']['attorney_review_status'],
                $fixtureName
            );
            $this->assertFalse($fixture['legal_boundary']['execution_allowed'], $fixtureName);
            $this->assertStringContainsString(
                'attorney review, citation verification, client authorization, and final legal judgment remain required',
                $fixture['legal_boundary']['disclaimer'],
                $fixtureName
            );
        }
    }
}
