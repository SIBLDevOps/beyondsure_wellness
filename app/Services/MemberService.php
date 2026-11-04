<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Region;
use Illuminate\Support\Facades\DB;
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
     * Starts a region: creates the member who is the top of its tree (no sponsor, no placement parent). Only the admin
     * does this. The region row is locked while checking, so two clicks, or two admins, can never make two roots.
     *
     * @throws \DomainException if the region already has a root, or the mobile number already belongs to someone
     */
    public function createRoot(Region $region, string $name, string $phone, ?string $email): Member
    {
        return DB::transaction(function () use ($region, $name, $phone, $email) {
            $locked = Region::withoutGlobalScopes()->whereKey($region->id)->lockForUpdate()->firstOrFail();

            if ($locked->root()) {
                throw new \DomainException("The {$locked->name} region already has a root.");
            }

            if ($existing = Member::where('phone', $phone)->first()) {
                $where = $existing->region?->name ?? 'another region';
                throw new \DomainException("That mobile number already belongs to a member in the {$where} region. A mobile number can belong to only one region.");
            }

            return Member::create([
                'region_id' => $locked->id,
                'member_code' => $this->generateMemberCode(),
                'name' => $name,
                'phone' => $phone,
                'email' => $email ?: null,
                'status' => 'inactive',
            ]);
        });
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
