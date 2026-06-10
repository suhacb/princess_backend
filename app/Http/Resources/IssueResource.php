<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'project_id'        => $this->project_id,
            'stage_id'          => $this->stage_id,
            'issue_type'        => $this->issue_type,
            'title'             => $this->title,
            'description'       => $this->description,
            'priority'          => $this->priority,
            'status'            => $this->status,
            'raised_at'         => $this->raised_at,
            'escalated_at'      => $this->escalated_at,
            'escalation_reason' => $this->escalation_reason,
            'resolved_at'       => $this->resolved_at,
            'resolution'        => $this->resolution,
            'raised_by'         => new PersonResource($this->whenLoaded('raisedBy')),
            'assigned_to'       => new PersonResource($this->whenLoaded('assignedTo')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
