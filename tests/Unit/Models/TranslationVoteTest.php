<?php

namespace Tests\Unit\Models;

use App\Models\TranslationVote;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class TranslationVoteTest extends TestCase
{
    public function test_fillable_attributes(): void
    {
        $vote = new TranslationVote;
        $fillable = $vote->getFillable();

        $this->assertContains('proposal_id', $fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('vote', $fillable);
    }

    public function test_proposal_relation_defined(): void
    {
        $vote = new TranslationVote;
        $relation = $vote->proposal();
        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
