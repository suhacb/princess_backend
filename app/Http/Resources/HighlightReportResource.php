<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HighlightReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'stage_id'             => $this->stage_id,
            'ref'                  => $this->ref,
            'title'                => $this->title,
            'period_from'          => $this->period_from?->toDateString(),
            'period_to'            => $this->period_to?->toDateString(),
            'status'               => $this->status,
            'budget_status'        => $this->budget_status,
            'schedule_status'      => $this->schedule_status,
            'this_period_work'     => $this->this_period_work,
            'next_period_work'     => $this->next_period_work,
            'issues_summary'       => $this->issues_summary,
            'risks_summary'        => $this->risks_summary,
            'quality_summary'      => $this->quality_summary,
            'business_case_review' => $this->business_case_review,
            'forecast_finish'      => $this->forecast_finish?->toDateString(),
            'submitted_at'         => $this->submitted_at,
            'submitted_by'         => new PersonResource($this->whenLoaded('submittedBy')),
            'approved_at'          => $this->approved_at,
            'approved_by'          => new PersonResource($this->whenLoaded('approvedBy')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
