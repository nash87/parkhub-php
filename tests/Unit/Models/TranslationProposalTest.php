<?php

namespace Tests\Unit\Models;

use App\Models\TranslationProposal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class TranslationProposalTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $proposal = new TranslationProposal;
        $fillable = $proposal->getFillable();

        $this->assertContains('language', $fillable);
        $this->assertContains('key', $fillable);
        $this->assertContains('current_value', $fillable);
        $this->assertContains('proposed_value', $fillable);
        $this->assertContains('proposed_by', $fillable);
        $this->assertContains('status', $fillable);
    }

    public function test_proposer_relation_defined(): void
    {
        $proposal = new TranslationProposal;
        $relation = $proposal->proposer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_reviewer_relation_defined(): void
    {
        $proposal = new TranslationProposal;
        $relation = $proposal->reviewer();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }

    public function test_votes_relation_defined(): void
    {
        $proposal = new TranslationProposal;
        $relation = $proposal->votes();
        $this->assertInstanceOf(HasMany::class, $relation);
    }
}
