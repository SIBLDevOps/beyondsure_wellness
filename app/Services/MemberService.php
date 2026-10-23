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
     *
     * $leg ('left' | 'right') lets the sponsor choose which side the new member goes on: the new
     * member lands in that leg only — directly under the sponsor if that slot is open, otherwise
     * at the first open slot (breadth-first) inside that leg's subtree. They never cross to the
     * other leg.
     */
    public function placeUnderSponsor(?Member $sponsor, ?string $leg = null): array
    {
        if (! $sponsor) {
            return ['placement_id' => null, 'position' => null, 'path' => null];
        }

        if (in_array($leg, ['left', 'right'], true)) {
            $legRoot = Member::where('placement_id', $sponsor->id)->where('position', $leg)->first();

            if (! $legRoot) {
                return $this->slot($sponsor, $leg);
            }

            return $this->firstOpenSlotFrom($legRoot);
        }

        return $this->firstOpenSlotFrom($sponsor);
    }

    private function firstOpenSlotFrom(Member $sponsor): array
    {
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
