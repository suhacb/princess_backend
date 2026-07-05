<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcceptanceCriterionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'project_id'             => $this->project_id,
            'requirement_id'         => $this->requirement_id,
            'ref'                    => $this->ref,
            'title'                  => $this->title,
            'description'            => $this->description,
            'measurement_method'     => $this->measurement_method,
            'acceptance_threshold'   => $this->acceptance_threshold,
            'verifier'               => new PersonResource($this->whenLoaded('verifier')),
            'verification_method'    => $this->verification_method,
            'status'                 => $this->status,
            'version'                => $this->version,
            'approved_by'            => new PersonResource($this->whenLoaded('approvedBy')),
            'approved_at'            => $this->approved_at,
            'supplier_passed'        => $this->supplier_passed,
            'supplier_passed_at'     => $this->supplier_passed_at,
            'supplier_decision'      => $this->supplier_decision,
            'supplier_decided_by'    => new PersonResource($this->whenLoaded('supplierDecidedBy')),
            'supplier_decided_at'    => $this->supplier_decided_at,
            'supplier_decision_note' => $this->supplier_decision_note,
            'client_passed'          => $this->client_passed,
            'client_passed_at'       => $this->client_passed_at,
            'client_decision'        => $this->client_decision,
            'client_decided_by'      => new PersonResource($this->whenLoaded('clientDecidedBy')),
            'client_decided_at'      => $this->client_decided_at,
            'client_decision_note'   => $this->client_decision_note,
            'accepted_at'            => $this->accepted_at,
            'requirement'            => new RequirementResource($this->whenLoaded('requirement')),
            'created_by'             => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'             => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
