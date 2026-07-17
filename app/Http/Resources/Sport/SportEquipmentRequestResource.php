<?php

namespace App\Http\Resources\Sport;

use App\Models\SportEquipmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SportEquipmentRequest */
class SportEquipmentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestCode' => $this->request_code,
            'equipmentItemId' => $this->equipment_item_id,
            'coachUserId' => $this->coach_user_id,
            'teamId' => $this->team_id,
            'requestedQuantity' => (int) $this->requested_quantity,
            'approvedQuantity' => $this->approved_quantity !== null ? (int) $this->approved_quantity : null,
            'issuedQuantity' => (int) $this->issued_quantity,
            'returnedQuantity' => (int) $this->returned_quantity,
            'damagedQuantity' => (int) $this->damaged_quantity,
            'missingQuantity' => (int) $this->missing_quantity,
            'purpose' => $this->purpose,
            'requiredDate' => $this->required_date?->toDateString(),
            'expectedReturnDate' => $this->expected_return_date?->toDateString(),
            'status' => $this->status,
            'adminNote' => $this->admin_note,
            'rejectedReason' => $this->rejected_reason,
            'reviewedByUserId' => $this->reviewed_by_user_id,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'issuedByUserId' => $this->issued_by_user_id,
            'issuedAt' => $this->issued_at?->toISOString(),
            'returnedByUserId' => $this->returned_by_user_id,
            'returnedAt' => $this->returned_at?->toISOString(),
            'item' => $this->whenLoaded('item', fn (): array => SportEquipmentItemResource::make($this->item)->resolve($request)),
            'coach' => $this->whenLoaded('coach', fn (): array => [
                'id' => $this->coach?->id,
                'firstName' => $this->coach?->first_name,
                'lastName' => $this->coach?->last_name,
                'username' => $this->coach?->username,
                'email' => $this->coach?->email,
            ]),
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team?->id,
                'teamCode' => $this->team?->team_code,
                'name' => $this->team?->name,
                'shortName' => $this->team?->short_name,
            ]),
            'reviewedBy' => $this->whenLoaded('reviewedBy', fn (): array => [
                'id' => $this->reviewedBy?->id,
                'firstName' => $this->reviewedBy?->first_name,
                'lastName' => $this->reviewedBy?->last_name,
                'username' => $this->reviewedBy?->username,
                'email' => $this->reviewedBy?->email,
            ]),
            'issuedBy' => $this->whenLoaded('issuedBy', fn (): array => [
                'id' => $this->issuedBy?->id,
                'firstName' => $this->issuedBy?->first_name,
                'lastName' => $this->issuedBy?->last_name,
                'username' => $this->issuedBy?->username,
                'email' => $this->issuedBy?->email,
            ]),
            'returnedBy' => $this->whenLoaded('returnedBy', fn (): array => [
                'id' => $this->returnedBy?->id,
                'firstName' => $this->returnedBy?->first_name,
                'lastName' => $this->returnedBy?->last_name,
                'username' => $this->returnedBy?->username,
                'email' => $this->returnedBy?->email,
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
