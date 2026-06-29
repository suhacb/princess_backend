<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QaDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'type'             => $this->type,
            'category'         => $this->category,
            'title'            => $this->title,
            'version'          => $this->version,
            'description'      => $this->description,
            'metadata'         => $this->metadata,
            'documentable'     => $this->whenLoaded('documentable', fn () => [
                'type' => class_basename($this->documentable),
                'id'   => $this->documentable_id,
            ]),
            'status'           => $this->status,
            'supersedes_id'    => $this->supersedes_id,
            'review_notes'     => $this->review_notes,
            'reviewed_by'      => new PersonResource($this->whenLoaded('reviewedBy')),
            'reviewed_at'      => $this->reviewed_at,
            'confirmed_by'     => new PersonResource($this->whenLoaded('confirmedBy')),
            'confirmed_at'     => $this->confirmed_at,
            'requirements'     => RequirementResource::collection($this->whenLoaded('requirements')),
            'created_by'       => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'       => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
