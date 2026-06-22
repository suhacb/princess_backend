<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestSessionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'project_id'   => $this->project_id,
            'ref'          => $this->ref,
            'title'        => $this->title,
            'description'  => $this->description,
            'planned_date' => $this->planned_date,
            'team_type'    => $this->team_type,
            'assignee'     => new PersonResource($this->whenLoaded('assignee')),
            'status'       => $this->status,
            'scenarios'    => TestScenarioResource::collection($this->whenLoaded('scenarios')),
            'created_by'   => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'   => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
