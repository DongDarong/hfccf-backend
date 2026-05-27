<?php

namespace App\Support;

use App\Models\PreschoolGuardian;
use App\Models\PreschoolStudent;
use App\Models\PreschoolStudentGuardian;
use Illuminate\Support\Collection;

final class PreschoolGuardianSnapshotService
{
    /**
     * Keep normalized guardian data as the preferred snapshot so legacy
     * student fields only act as compatibility fallbacks during cleanup.
     */
    public function activeRelationships(PreschoolStudent $student): Collection
    {
        $today = now()->toDateString();

        return $student->loadMissing(['studentGuardians.guardian'])->studentGuardians
            ->filter(function (PreschoolStudentGuardian $relationship) use ($today): bool {
                $guardian = $relationship->guardian;

                if ($relationship->status !== PreschoolGuardianStatus::ACTIVE) {
                    return false;
                }

                if (! $guardian || $guardian->status !== PreschoolGuardianStatus::ACTIVE || $guardian->trashed()) {
                    return false;
                }

                if ($relationship->starts_at && $relationship->starts_at->toDateString() > $today) {
                    return false;
                }

                if ($relationship->ends_at && $relationship->ends_at->toDateString() < $today) {
                    return false;
                }

                return true;
            })
            ->sort(static function (PreschoolStudentGuardian $left, PreschoolStudentGuardian $right): int {
                if ($left->is_primary !== $right->is_primary) {
                    return $left->is_primary ? -1 : 1;
                }

                $leftPriority = (int) ($left->emergency_priority ?? 999999);
                $rightPriority = (int) ($right->emergency_priority ?? 999999);

                if ($leftPriority !== $rightPriority) {
                    return $leftPriority <=> $rightPriority;
                }

                $leftCreated = $left->created_at?->timestamp ?? 0;
                $rightCreated = $right->created_at?->timestamp ?? 0;

                return $leftCreated <=> $rightCreated;
            })
            ->values();
    }

    public function preferredRelationship(PreschoolStudent $student): ?PreschoolStudentGuardian
    {
        return $this->activeRelationships($student)->first();
    }

    /**
     * Return the student guardian snapshot that API consumers should prefer.
     * If there is no active normalized relationship, we fall back to the legacy
     * compatibility fields without letting them override normalized data.
     */
    public function preferredGuardianSnapshot(PreschoolStudent $student): array
    {
        $relationship = $this->preferredRelationship($student);

        if (! $relationship) {
            return $this->legacyGuardianSnapshot($student) + [
                'guardianId' => null,
                'relationshipId' => null,
                'relationshipType' => null,
                'isPrimary' => false,
                'canPickup' => false,
                'emergencyPriority' => null,
                'relationshipStatus' => null,
                'source' => 'legacy',
            ];
        }

        $guardian = $relationship->guardian;

        return [
            'guardianId' => $guardian?->id,
            'guardianName' => $guardian?->full_name ?? $student->guardian_name,
            'guardianPhone' => $guardian?->phone ?? $student->guardian_phone,
            'guardianSecondaryPhone' => $guardian?->secondary_phone,
            'guardianEmail' => $guardian?->email,
            'relationshipId' => $relationship->id,
            'relationshipType' => $relationship->relationship_type,
            'isPrimary' => (bool) $relationship->is_primary,
            'canPickup' => (bool) $relationship->can_pickup,
            'emergencyPriority' => $relationship->emergency_priority,
            'relationshipStatus' => $relationship->status,
            'source' => 'normalized',
        ];
    }

    public function legacyGuardianSnapshot(PreschoolStudent $student): array
    {
        return [
            'guardianName' => $student->guardian_name,
            'guardianPhone' => $student->guardian_phone,
        ];
    }

    /**
     * Compare the legacy compatibility fields against the preferred normalized
     * guardian snapshot so staff can spot stale student-row values quickly.
     */
    public function legacyMismatch(PreschoolStudent $student): array
    {
        $preferred = $this->preferredGuardianSnapshot($student);

        if (($preferred['source'] ?? '') !== 'normalized') {
            return [];
        }

        $diffs = [];
        $legacyName = $this->normalizeText($student->guardian_name);
        $legacyPhone = $this->normalizePhone($student->guardian_phone);
        $normalizedName = $this->normalizeText($preferred['guardianName'] ?? '');
        $normalizedPhone = $this->normalizePhone($preferred['guardianPhone'] ?? '');

        if ($legacyName !== '' && $legacyName !== $normalizedName) {
            $diffs['guardianName'] = [
                'legacy' => $student->guardian_name,
                'normalized' => $preferred['guardianName'] ?? null,
            ];
        }

        if ($legacyPhone !== '' && $legacyPhone !== $normalizedPhone) {
            $diffs['guardianPhone'] = [
                'legacy' => $student->guardian_phone,
                'normalized' => $preferred['guardianPhone'] ?? null,
            ];
        }

        return $diffs;
    }

    public function guardianSnapshot(PreschoolGuardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'fullName' => $guardian->full_name,
            'phone' => $guardian->phone,
            'secondaryPhone' => $guardian->secondary_phone,
            'email' => $guardian->email,
            'status' => $guardian->status,
            'relationshipsCount' => (int) ($guardian->relationships_count ?? 0),
            'activeRelationshipsCount' => (int) ($guardian->active_relationships_count ?? 0),
            'relationshipTypes' => collect($guardian->studentGuardians ?? [])
                ->pluck('relationship_type')
                ->filter()
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function normalizePhone(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) ($value ?? '')) ?: '';
    }
}
