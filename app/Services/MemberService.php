<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Str;

class MemberService
{
    public function generateMemberCode(): string
    {
        do {
            $code = 'BS'.strtoupper(Str::random(6));
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }

    /**
     * Find the first open left/right slot under the given sponsor via breadth-first
     * search of the placement tree, and return where a new member should be placed.
     * If no sponsor is given, the new member becomes a root (no placement parent).
     */
    public function placeUnderSponsor(?Member $sponsor): array
    {
        if (! $sponsor) {
            return ['placement_id' => null, 'position' => null, 'path' => null];
        }

        $queue = [$sponsor];

        while ($queue) {
            /** @var Member $node */
            $node = array_shift($queue);

            $left = Member::where('placement_id', $node->id)->where('position', 'left')->first();
            $right = Member::where('placement_id', $node->id)->where('position', 'right')->first();

            if (! $left) {
                return $this->slot($node, 'left');
            }

            if (! $right) {
                return $this->slot($node, 'right');
            }

            $queue[] = $left;
            $queue[] = $right;
        }

        // Unreachable in practice — BFS over an infinite binary tree always finds a slot.
        return ['placement_id' => $sponsor->id, 'position' => 'left', 'path' => $this->buildPath($sponsor)];
    }

    private function slot(Member $parent, string $position): array
    {
        return [
            'placement_id' => $parent->id,
            'position' => $position,
            'path' => $this->buildPath($parent),
        ];
    }

    private function buildPath(Member $parent): string
    {
        return ($parent->path ?? '').$parent->id.'.';
    }
}
