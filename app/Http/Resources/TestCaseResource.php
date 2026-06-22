<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'test_scenario_id' => $this->test_scenario_id,
            'project_id'       => $this->project_id,
            'ref'              => $this->ref,
            'title'            => $this->title,
            'steps'            => $this->steps,
            'expected_result'  => $this->expected_result,
            'created_by'       => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'       => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
