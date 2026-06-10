<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectProductDescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                            => $this->id,
            'project_id'                    => $this->project_id,
            'title'                         => $this->title,
            'purpose'                       => $this->purpose,
            'composition'                   => $this->composition,
            'derivation'                    => $this->derivation,
            'format_and_presentation'       => $this->format_and_presentation,
            'quality_criteria'              => $this->quality_criteria,
            'quality_tolerance'             => $this->quality_tolerance,
            'quality_method'                => $this->quality_method,
            'quality_skills_required'       => $this->quality_skills_required,
            'quality_responsibilities'      => $this->quality_responsibilities,
            'customer_quality_expectations' => $this->customer_quality_expectations,
            'acceptance_criteria'           => $this->acceptance_criteria,
            'acceptance_methods'            => $this->acceptance_methods,
            'acceptance_responsibilities'   => $this->acceptance_responsibilities,
            'version'                       => $this->version,
            'baselined_at'                  => $this->baselined_at,
            'created_by'                    => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'                    => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'                    => $this->created_at,
            'updated_at'                    => $this->updated_at,
        ];
    }
}
