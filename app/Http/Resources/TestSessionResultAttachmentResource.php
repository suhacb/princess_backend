<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestSessionResultAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'step_index'      => $this->step_index,
            'file_name'       => $this->file_name,
            'file_size_bytes' => $this->file_size_bytes,
            'mime_type'       => $this->mime_type,
            'created_by'      => new PersonResource($this->whenLoaded('createdBy')),
            'created_at'      => $this->created_at,
        ];
    }
}
