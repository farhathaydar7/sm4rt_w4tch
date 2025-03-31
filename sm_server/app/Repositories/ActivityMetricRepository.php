<?php

namespace App\Repositories;

use App\Models\ActivityMetric;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ActivityMetricRepository implements ActivityMetricRepositoryInterface
{
    protected $model;

    public function __construct(ActivityMetric $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(int $id): ?ActivityMetric
    {
        return $this->model->find($id);
    }

    public function create(array $data): ActivityMetric
    {
        return $this->model->create($data);
    }

    public function createMany(array $data): bool
    {
        return $this->model->insert($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    public function getByUserId(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)->get();
    }

    public function getByCsvUploadId(int $csvUploadId): Collection
    {
        return $this->model->where('csv_upload_id', $csvUploadId)->get();
    }

    public function getByDateRange(int $userId, string $startDate, string $endDate): Collection
    {
        return $this->model->where('user_id', $userId)
            ->whereBetween('activity_date', [$startDate, $endDate])
            ->orderBy('activity_date')
            ->get();
    }

    public function findByUserAndDate(int $userId, string $date): ?ActivityMetric
    {
        return $this->model->where('user_id', $userId)
            ->where('activity_date', $date)
            ->first();
    }
}
