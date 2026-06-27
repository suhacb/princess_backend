<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'project_id'       => $this->project_id,
            'stage_id'         => $this->stage_id,
            'stage'            => new StageResource($this->whenLoaded('stage')),
            'work_package_id'  => $this->work_package_id,
            'work_package'     => new WorkPackageResource($this->whenLoaded('workPackage')),
            'title'            => $this->title,
            'description'      => $this->description,
            'assigned_to'      => $this->assigned_to,
            'assigned'         => new PersonResource($this->whenLoaded('assignedTo')),
            'due_date'         => $this->due_date,
            'status'           => $this->status,
            'priority'         => $this->priority,
            'created_by'       => new PersonResource($this->whenLoaded('createdBy')),
            'updated_by'       => new PersonResource($this->whenLoaded('updatedBy')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
