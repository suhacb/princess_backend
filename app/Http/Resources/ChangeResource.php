<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'project_id'         => $this->project_id,
            'issue_id'           => $this->issue_id,
            'request_type'       => $this->request_type,
            'title'              => $this->title,
            'description'        => $this->description,
            'impact_assessment'  => $this->impact_assessment,
            'priority'           => $this->priority,
            'status'             => $this->status,
            'raised_at'          => $this->raised_at,
            'decision_at'        => $this->decision_at,
            'decision_rationale' => $this->decision_rationale,
            'implementation_due' => $this->implementation_due?->toDateString(),
            'implemented_at'     => $this->implemented_at?->toDateString(),
            'raised_by'          => new PersonResource($this->whenLoaded('raisedBy')),
            'decision_by'        => new PersonResource($this->whenLoaded('decidedBy')),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
