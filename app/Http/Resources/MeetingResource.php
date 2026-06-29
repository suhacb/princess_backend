<?php

namespace App\Http\Resources;

use App\Http\Resources\QaDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'project_id'           => $this->project_id,
            'title'                => $this->title,
            'date_time'            => $this->date_time,
            'agenda'               => $this->agenda,
            'minutes_body'         => $this->minutes_body,
            'document'             => new QaDocumentResource($this->whenLoaded('document')),
            'attendees'            => PersonResource::collection($this->whenLoaded('attendees')),
            'action_items'         => MeetingActionItemResource::collection($this->whenLoaded('actionItems')),
            'action_items_open'    => $this->when(isset($this->action_items_open), $this->action_items_open),
            'action_items_closed'  => $this->when(isset($this->action_items_closed), $this->action_items_closed),
            'created_by'           => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'           => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
