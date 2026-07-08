<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromptTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'version'      => $this->version,
            'body'         => $this->body,
            'placeholders' => $this->placeholders(),
            'active'       => $this->active,
            'created_by'   => new PersonResource($this->whenLoaded('createdBy')),
            'created_at'   => $this->created_at,
        ];
    }
}
