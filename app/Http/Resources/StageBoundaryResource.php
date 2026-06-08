<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageBoundaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'stage_id'          => $this->stage_id,
            'type'              => $this->type,
            'status'            => $this->status,
            'title'             => $this->title,
            'notes'             => $this->notes,
            'next_stage_id'     => $this->next_stage_id,
            'exception_summary' => $this->exception_summary,
            'submitted_at'      => $this->submitted_at,
            'submitted_by'      => new PersonResource($this->whenLoaded('submittedBy')),
            'approved_at'       => $this->approved_at,
            'approved_by'       => new PersonResource($this->whenLoaded('approvedBy')),
            'created_by'        => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'        => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
