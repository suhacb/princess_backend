<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'requirement_id'  => $this->requirement_id,
            'version_number'  => $this->version_number,
            'title'           => $this->title,
            'description'     => $this->description,
            'type'            => $this->type,
            'priority'        => $this->priority,
            'status'          => $this->status,
            'role'            => $this->role,
            'action'          => $this->action,
            'benefit'         => $this->benefit,
            'owner'           => new PersonResource($this->whenLoaded('owner')),
            'created_by'      => new PersonResource($this->whenLoaded('createdBy')),
            'created_at'      => $this->created_at,
        ];
    }
}
