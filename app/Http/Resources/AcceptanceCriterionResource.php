<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcceptanceCriterionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'requirement_id'       => $this->requirement_id,
            'ref'                  => $this->ref,
            'description'          => $this->description,
            'measurement_method'   => $this->measurement_method,
            'acceptance_threshold' => $this->acceptance_threshold,
            'status'               => $this->status,
            'approved_by'          => new PersonResource($this->whenLoaded('approvedBy')),
            'approved_at'          => $this->approved_at,
            'supplier_passed'      => $this->supplier_passed,
            'supplier_passed_at'   => $this->supplier_passed_at,
            'client_passed'        => $this->client_passed,
            'client_passed_at'     => $this->client_passed_at,
            'accepted_at'          => $this->accepted_at,
            'requirement'          => new RequirementResource($this->whenLoaded('requirement')),
            'created_by'           => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'           => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
