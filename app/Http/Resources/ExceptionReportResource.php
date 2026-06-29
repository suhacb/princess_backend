<?php

namespace App\Http\Resources;

use App\Http\Resources\QaDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExceptionReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'project_id'     => $this->project_id,
            'stage_id'       => $this->stage_id,
            'ref'            => $this->ref,
            'title'          => $this->title,
            'trigger_type'   => $this->trigger_type,
            'description'    => $this->description,
            'cause'          => $this->cause,
            'impact'         => $this->impact,
            'options'        => $this->options,
            'recommendation' => $this->recommendation,
            'status'         => $this->status,
            'document'       => new QaDocumentResource($this->whenLoaded('document')),
            'board_decision' => $this->board_decision,
            'decided_at'     => $this->decided_at,
            'decided_by'     => new PersonResource($this->whenLoaded('decidedBy')),
            'submitted_at'   => $this->submitted_at,
            'submitted_by'   => new PersonResource($this->whenLoaded('submittedBy')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
