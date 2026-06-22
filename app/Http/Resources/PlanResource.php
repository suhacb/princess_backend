<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'project_id'               => $this->project_id,
            'type'                     => $this->type,
            'name'                     => $this->name,
            'description'              => $this->description,
            'stage_id'                 => $this->stage_id,
            'replaces_plan_id'         => $this->replaces_plan_id,
            'planned_start'            => $this->planned_start,
            'planned_end'              => $this->planned_end,
            'actual_start'             => $this->actual_start,
            'actual_end'               => $this->actual_end,
            'tolerance_time'           => $this->tolerance_time,
            'tolerance_cost'           => $this->tolerance_cost,
            'tolerance_scope'          => $this->tolerance_scope,
            'tolerance_quality'        => $this->tolerance_quality,
            'tolerance_risk'           => $this->tolerance_risk,
            'tolerance_benefits'       => $this->tolerance_benefits,
            'assumptions'              => $this->assumptions,
            'external_dependencies'    => $this->external_dependencies,
            'monitoring_and_reporting' => $this->monitoring_and_reporting,
            'status'                   => $this->status,
            'approved_by'              => new PersonResource($this->whenLoaded('approvedBy')),
            'approved_at'              => $this->approved_at,
            'created_by'               => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'               => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
