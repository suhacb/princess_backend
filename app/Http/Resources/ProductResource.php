<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'project_id'              => $this->project_id,
            'parent_id'               => $this->parent_id,
            'identifier'              => $this->identifier,
            'title'                   => $this->title,
            'purpose'                 => $this->purpose,
            'composition'             => $this->composition,
            'derivation'              => $this->derivation,
            'format_and_presentation' => $this->format_and_presentation,
            'type'                    => $this->type,
            'quality_criteria'        => $this->quality_criteria,
            'quality_tolerance'       => $this->quality_tolerance,
            'quality_method'          => $this->quality_method,
            'quality_skills_required' => $this->quality_skills_required,
            'quality_responsibilities' => $this->quality_responsibilities,
            'status'                  => $this->status,
            'version'                 => $this->version,
            'baselined_at'            => $this->baselined_at,
            'children'                => ProductResource::collection($this->whenLoaded('children')),
            'created_by'              => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'              => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
