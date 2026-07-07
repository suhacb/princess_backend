<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestSessionResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'test_session_id' => $this->test_session_id,
            'test_scenario'   => new TestScenarioResource($this->whenLoaded('testScenario')),
            'test_case'       => new TestCaseResource($this->whenLoaded('testCase')),
            'result'          => $this->result,
            'step_results'    => $this->step_results,
            'notes'           => $this->notes,
            'defect_ref'      => $this->defect_ref,
            'executed_at'     => $this->executed_at,
        ];
    }
}
