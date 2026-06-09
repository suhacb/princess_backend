<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'project_id'     => $this->project_id,
            'stage_id'       => $this->stage_id,
            'category'       => $this->category,
            'description'    => $this->description,
            'recommendation' => $this->recommendation,
            'source'         => $this->source,
            'raised_at'      => $this->raised_at,
            'raised_by'      => new PersonResource($this->whenLoaded('raisedBy')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
