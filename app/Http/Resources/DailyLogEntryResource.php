<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyLogEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'stage_id'   => $this->stage_id,
            'date'       => $this->date?->toDateString(),
            'entry_type' => $this->entry_type,
            'body'       => $this->body,
            'source'     => $this->source,
            'author'     => new PersonResource($this->whenLoaded('author')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
