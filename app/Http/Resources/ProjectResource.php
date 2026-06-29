<?php

namespace App\Http\Resources;

use App\Http\Resources\QaDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'reference'     => $this->reference,
            'description'   => $this->description,
            'status'        => $this->status,
            'version'       => $this->version,
            'document'      => new QaDocumentResource($this->whenLoaded('document')),
            'current_stage' => new StageResource($this->whenLoaded('currentStage')),
            'stages'        => StageResource::collection($this->whenLoaded('stages')),
            'stages_count'  => $this->whenCounted('stages'),
            'planned_start' => $this->planned_start?->toDateString(),
            'planned_end'   => $this->planned_end?->toDateString(),
            'actual_start'  => $this->actual_start?->toDateString(),
            'actual_end'    => $this->actual_end?->toDateString(),
            'tolerances'    => [
                'time'    => $this->tolerance_time,
                'cost'    => $this->tolerance_cost,
                'scope'   => $this->tolerance_scope,
                'risk'    => $this->tolerance_risk,
                'quality' => $this->tolerance_quality,
                'benefit' => $this->tolerance_benefit,
            ],
            'created_by'    => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'    => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
