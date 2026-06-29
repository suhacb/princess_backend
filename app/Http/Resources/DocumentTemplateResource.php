<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'parent_id'  => $this->parent_id,
            'name'       => $this->name,
            'category'   => $this->category,
            'type'       => $this->type,
            'has_file'   => $this->s3_key !== null,
            'settings'   => $this->settings ?? [],
            'created_by' => new PersonResource($this->whenLoaded('createdBy')),
            'children'   => DocumentTemplateResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
