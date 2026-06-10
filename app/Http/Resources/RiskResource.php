<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'stage_id'             => $this->stage_id,
            'title'                => $this->title,
            'description'          => $this->description,
            'category'             => $this->category,
            'probability'          => $this->probability,
            'impact'               => $this->impact,
            'risk_score'           => $this->riskScore(),
            'proximity'            => $this->proximity,
            'response_type'        => $this->response_type,
            'response_action'      => $this->response_action,
            'residual_probability' => $this->residual_probability,
            'residual_impact'      => $this->residual_impact,
            'residual_risk_score'  => $this->residualRiskScore(),
            'status'               => $this->status,
            'raised_at'            => $this->raised_at,
            'owner'                => new PersonResource($this->whenLoaded('owner')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
