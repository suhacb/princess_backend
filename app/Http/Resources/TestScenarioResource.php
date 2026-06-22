<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'project_id'               => $this->project_id,
            'ref'                      => $this->ref,
            'title'                    => $this->title,
            'description'              => $this->description,
            'preconditions'            => $this->preconditions,
            'type'                     => $this->type,
            'status'                   => $this->status,
            'is_testable'              => $this->is_testable,
            'testable_notes'           => $this->testable_notes,
            'test_cases'               => TestCaseResource::collection($this->whenLoaded('testCases')),
            'acceptance_criteria'      => AcceptanceCriterionResource::collection($this->whenLoaded('acceptanceCriteria')),
            'created_by'               => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'               => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
