<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualityRegisterEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'project_id'     => $this->project_id,
            'stage_id'       => $this->stage_id,
            'product_name'   => $this->product_name,
            'quality_method' => $this->quality_method,
            'planned_date'   => $this->planned_date?->toDateString(),
            'actual_date'    => $this->actual_date?->toDateString(),
            'reviewers'      => $this->reviewers,
            'result'         => $this->result,
            'issues_raised'  => $this->issues_raised,
            'sign_off_at'    => $this->sign_off_at,
            'sign_off_by'    => new PersonResource($this->whenLoaded('signedOffBy')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
