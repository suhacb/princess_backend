<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'project_id'  => $this->project_id,
            'type'        => $this->type,
            'parent_id'   => $this->parent_id,
            'ref'         => $this->ref,
            'title'       => $this->title,
            'description' => $this->description,
            'role'        => $this->role,
            'action'      => $this->action,
            'benefit'     => $this->benefit,
            'priority'    => $this->priority,
            'status'      => $this->status,
            'source'      => $this->source,
            'owner'       => new PersonResource($this->whenLoaded('owner')),
            'version'     => $this->version,
            'approved_by' => new PersonResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at,
            'children'    => RequirementResource::collection($this->whenLoaded('children')),
            'acceptance_criteria' => AcceptanceCriterionResource::collection($this->whenLoaded('acceptanceCriteria')),
            'created_by'  => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'  => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
