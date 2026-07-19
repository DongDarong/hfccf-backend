<?php

namespace App\Http\Resources\Sport;

use App\Models\SportEquipmentAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportEquipmentAssignment */
class SportEquipmentAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignmentCode' => $this->assignment_code,
            'equipmentRequestId' => $this->equipment_request_id,
            'equipmentItemId' => $this->equipment_item_id,
            'teamId' => $this->team_id,
            'coachUserId' => $this->coach_user_id,
            'assignedQuantity' => (int) $this->assigned_quantity,
            'returnedQuantity' => (int) $this->returned_quantity,
            'damagedQuantity' => (int) $this->damaged_quantity,
            'missingQuantity' => (int) $this->missing_quantity,
            'status' => $this->status,
            'assignedAt' => $this->assigned_at?->toISOString(),
            'expectedReturnAt' => $this->expected_return_at?->toISOString(),
            'returnedAt' => $this->returned_at?->toISOString(),
            'assignedByUserId' => $this->assigned_by_user_id,
            'returnedByUserId' => $this->returned_by_user_id,
            'notes' => $this->notes,
            'request' => $this->whenLoaded('request', fn (): array => [
                'id' => $this->request?->id,
                'requestCode' => $this->request?->request_code,
                'status' => $this->request?->status,
            ]),
            'item' => $this->whenLoaded('item', fn (): array => SportEquipmentItemResource::make($this->item)->resolve($request)),
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'coach' => $this->whenLoaded('coach', fn (): array => [
                'id' => $this->coach?->id,
                'firstName' => $this->coach?->first_name,
                'lastName' => $this->coach?->last_name,
                'username' => $this->coach?->username,
                'email' => $this->coach?->email,
            ]),
            'assignedBy' => $this->whenLoaded('assignedBy', fn (): array => [
                'id' => $this->assignedBy?->id,
                'firstName' => $this->assignedBy?->first_name,
                'lastName' => $this->assignedBy?->last_name,
                'username' => $this->assignedBy?->username,
            ]),
            'returnedBy' => $this->whenLoaded('returnedBy', fn (): array => [
                'id' => $this->returnedBy?->id,
                'firstName' => $this->returnedBy?->first_name,
                'lastName' => $this->returnedBy?->last_name,
                'username' => $this->returnedBy?->username,
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
