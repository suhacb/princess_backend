<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                                    => $this->id,
            'project_id'                            => $this->project_id,
            'plan_id'                               => $this->plan_id,
            'team_manager_id'                       => $this->team_manager_id,
            'team_manager'                          => new PersonResource($this->whenLoaded('teamManager')),
            'title'                                 => $this->title,
            'description'                           => $this->description,
            'techniques_and_processes'              => $this->techniques_and_processes,
            'development_interfaces'                => $this->development_interfaces,
            'operations_interfaces'                 => $this->operations_interfaces,
            'configuration_management_requirements' => $this->configuration_management_requirements,
            'constraints'                           => $this->constraints,
            'reporting_requirements'                => $this->reporting_requirements,
            'tolerance_time'                        => $this->tolerance_time,
            'tolerance_cost'                        => $this->tolerance_cost,
            'tolerance_scope'                       => $this->tolerance_scope,
            'tolerance_quality'                     => $this->tolerance_quality,
            'tolerance_risk'                        => $this->tolerance_risk,
            'tolerance_benefits'                    => $this->tolerance_benefits,
            'planned_start'                         => $this->planned_start,
            'planned_end'                           => $this->planned_end,
            'actual_start'                          => $this->actual_start,
            'actual_end'                            => $this->actual_end,
            'status'                                => $this->status,
            'authorized_by'                         => new PersonResource($this->whenLoaded('authorizedBy')),
            'authorized_at'                         => $this->authorized_at,
            'products'                              => ProductResource::collection($this->whenLoaded('products')),
            'created_by'                            => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'                            => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'                            => $this->created_at,
            'updated_at'                            => $this->updated_at,
        ];
    }
}
