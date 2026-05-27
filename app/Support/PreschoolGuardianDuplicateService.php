<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class PreschoolGuardianDuplicateService
{
    public function __construct(
        private readonly PreschoolGuardianSnapshotService $snapshot,
    ) {}

    /**
     * Duplicate detection stays advisory only: no auto-merge, just reviewable
     * candidate groups that help staff spot repeated guardian records safely.
     */
    public function report(User $user): array
    {
        $this->ensureAdminAccess($user);

        $guardians = PreschoolGuardian::query()
            ->withCount([
                'studentGuardians as relationships_count',
                'activeStudentGuardians as active_relationships_count',
            ])
            ->with(['studentGuardians'])
            ->orderBy('full_name')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($guardians as $guardian) {
            $guardianData = $this->snapshot->guardianSnapshot($guardian);

            $signals = [
                'same_phone' => $this->normalizePhone($guardianData['phone'] ?? ''),
                'same_email' => $this->normalizeEmail($guardianData['email'] ?? ''),
                'same_name_phone' => $this->normalizeName($guardianData['fullName'] ?? '').'|'.$this->normalizePhone($guardianData['phone'] ?? ''),
            ];

            foreach ($signals as $signal => $key) {
                if ($key === '' || $key === '|') {
                    continue;
                }

                $this->addGroupCandidate($groups, $signal, $key, $guardianData);
            }

            foreach ($guardianData['relationshipTypes'] as $relationshipType) {
                $key = $this->normalizeName($guardianData['fullName'] ?? '').'|'.$this->normalizeText($relationshipType);
                if ($key === '|') {
                    continue;
                }

                $this->addGroupCandidate($groups, 'same_name_relationship_type', $key, $guardianData);
            }
        }

        $mergedGroups = [];

        foreach ($groups as $group) {
            $guardianIds = array_keys($group['guardianIds']);
            sort($guardianIds);

            if (count($guardianIds) < 2) {
                continue;
            }

            $groupKey = implode('-', $guardianIds);

            if (! isset($mergedGroups[$groupKey])) {
                $mergedGroups[$groupKey] = [
                    'guardianIds' => array_fill_keys($guardianIds, true),
                    'signals' => [],
                    'guardians' => [],
                ];
            }

            $mergedGroups[$groupKey]['signals'] = array_values(array_unique(array_merge($mergedGroups[$groupKey]['signals'], $group['signals'])));
            $mergedGroups[$groupKey]['guardians'] = $mergedGroups[$groupKey]['guardians'] + $group['guardians'];
        }

        $items = collect(array_values($mergedGroups))
            ->map(fn (array $group): array => $this->finalizeGroup($group))
            ->sortByDesc(static fn (array $group): int => match ($group['severity']) {
                'critical' => 0,
                'warning' => 1,
                default => 2,
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'candidateGroups' => count($items),
                'matchedGuardians' => collect($items)->pluck('guardianIds')->flatten()->unique()->count(),
                'strongSignalGroups' => collect($items)->filter(static fn (array $group): bool => in_array('same_phone', $group['signals'], true) || in_array('same_email', $group['signals'], true) || in_array('same_name_phone', $group['signals'], true))->count(),
                'reviewRecommended' => count($items) > 0,
            ],
            'items' => $items,
            'generatedAt' => now()->toISOString(),
        ];
    }

    private function addGroupCandidate(array &$groups, string $signal, string $key, array $guardian): void
    {
        $groupKey = $signal.'::'.$key;
        $guardianId = (string) ($guardian['id'] ?? '');
        if ($guardianId === '') {
            return;
        }

        if (! isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'groupKey' => $groupKey,
                'signals' => [$signal],
                'guardianIds' => [$guardianId => true],
                'guardians' => [$guardianId => $guardian],
            ];

            return;
        }

        $groups[$groupKey]['signals'][] = $signal;
        $groups[$groupKey]['guardianIds'][$guardianId] = true;
        $groups[$groupKey]['guardians'][$guardianId] = $guardian;
    }

    private function finalizeGroup(array $group): array
    {
        $guardianIds = array_keys($group['guardianIds']);
        sort($guardianIds);

        $guardians = collect($group['guardians'])
            ->values()
            ->map(function (array $guardian): array {
                return [
                    'id' => $guardian['id'],
                    'fullName' => $guardian['fullName'],
                    'phone' => $guardian['phone'],
                    'secondaryPhone' => $guardian['secondaryPhone'] ?? null,
                    'email' => $guardian['email'] ?? null,
                    'status' => $guardian['status'],
                    'relationshipsCount' => $guardian['relationshipsCount'] ?? 0,
                    'activeRelationshipsCount' => $guardian['activeRelationshipsCount'] ?? 0,
                    'relationshipTypes' => $guardian['relationshipTypes'] ?? [],
                ];
            })
            ->sortBy('fullName')
            ->values()
            ->all();

        $signals = collect($group['signals'])->unique()->values()->all();
        $severity = in_array('same_phone', $signals, true) || in_array('same_email', $signals, true) || in_array('same_name_phone', $signals, true)
            ? 'warning'
            : 'info';

        return [
            'guardianIds' => array_values($guardianIds),
            'guardians' => $guardians,
            'signals' => $signals,
            'severity' => $severity,
            'title' => 'Possible duplicate guardian records',
            'message' => 'Review these guardian records manually before linking more students or changing legacy contact data.',
        ];
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function normalizeName(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) ($value ?? ''))) ?: '');
    }

    private function normalizePhone(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
    }

    private function normalizeEmail(mixed $value): string
    {
        return mb_strtolower(trim((string) ($value ?? '')));
    }

    private function ensureAdminAccess(?User $user): void
    {
        abort_unless($user, Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        abort_unless(in_array($user->role_code, ['superadmin', 'adminpreschool'], true), Response::HTTP_FORBIDDEN, 'Forbidden.');
    }
}
