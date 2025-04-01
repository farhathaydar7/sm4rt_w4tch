<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CsvUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'file_path' => $this->file_path,
            'upload_date' => $this->upload_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'activity_metrics_count' => $this->whenLoaded('activityMetrics', function() {
                return $this->activityMetrics->count();
            }),
            'predictions_count' => $this->whenLoaded('predictions', function() {
                return $this->predictions->count();
            }),
        ];
    }
}
