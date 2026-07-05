<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcceptanceCriterionVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'acceptance_criterion_id' => $this->acceptance_criterion_id,
            'version_number'          => $this->version_number,
            'title'                   => $this->title,
            'description'             => $this->description,
            'verifier'                => new PersonResource($this->whenLoaded('verifier')),
            'verification_method'     => $this->verification_method,
            'status'                  => $this->status,
            'supplier_passed'         => $this->supplier_passed,
            'client_passed'           => $this->client_passed,
            'supplier_decision'       => $this->supplier_decision,
            'client_decision'         => $this->client_decision,
            'created_by'              => new PersonResource($this->whenLoaded('createdBy')),
            'created_at'              => $this->created_at,
        ];
    }
}
