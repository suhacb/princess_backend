<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'project_id'          => $this->project_id,
            'test_session_plan_id' => $this->test_session_plan_id,
            'ref'                 => $this->ref,
            'title'               => $this->title,
            'session_date'        => $this->session_date,
            'tester'              => new PersonResource($this->whenLoaded('tester')),
            'team_type'           => $this->team_type,
            'environment'         => $this->environment,
            'status'              => $this->status,
            'notes'               => $this->notes,
            'results'             => TestSessionResultResource::collection($this->whenLoaded('results')),
            'created_by'          => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'          => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
