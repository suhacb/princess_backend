<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingActionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'meeting_id'  => $this->meeting_id,
            'owner_id'    => $this->owner_id,
            'owner'       => new PersonResource($this->whenLoaded('owner')),
            'description' => $this->description,
            'due_date'    => $this->due_date,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
