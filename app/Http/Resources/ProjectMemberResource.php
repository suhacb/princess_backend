<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'person'     => new PersonResource($this->whenLoaded('person')),
            'role'       => $this->role,
            'side'       => $this->side,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
