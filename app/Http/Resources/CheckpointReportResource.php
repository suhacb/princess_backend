<?php

namespace App\Http\Resources;

use App\Http\Resources\QaDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckpointReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'work_package_id'      => $this->work_package_id,
            'ref'                  => $this->ref,
            'title'                => $this->title,
            'period_from'          => $this->period_from?->toDateString(),
            'period_to'            => $this->period_to?->toDateString(),
            'status'               => $this->status,
            'achievements'         => $this->achievements,
            'planned_next_period'  => $this->planned_next_period,
            'issues_this_period'   => $this->issues_this_period,
            'issues_forecast'      => $this->issues_forecast,
            'quality_notes'        => $this->quality_notes,
            'document'             => new QaDocumentResource($this->whenLoaded('document')),
            'submitted_at'         => $this->submitted_at,
            'submitted_by'         => new PersonResource($this->whenLoaded('submittedBy')),
            'acknowledged_at'      => $this->acknowledged_at,
            'acknowledged_by'      => new PersonResource($this->whenLoaded('acknowledgedBy')),
            'work_package'         => $this->whenLoaded('workPackage'),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
