<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'document_id'      => $this->document_id,
            'version_number'   => $this->version_number,
            'file_name'        => $this->file_name,
            'file_size_bytes'  => $this->file_size_bytes,
            'onlyoffice_key'   => $this->onlyoffice_key,
            'converted_md_key' => $this->converted_md_key,
            'comment'          => $this->comment,
            'created_by'       => new PersonResource($this->whenLoaded('createdBy')),
            'created_at'       => $this->created_at,
        ];
    }
}
