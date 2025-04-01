<?php

namespace App\Repositories;

use App\Models\Prediction;
use App\Repositories\Interfaces\PredictionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PredictionRepository implements PredictionRepositoryInterface
{
    protected $model;

    public function __construct(Prediction $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(int $id): ?Prediction
    {
        return $this->model->find($id);
    }

    public function create(array $data): Prediction
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

    public function getByType(int $userId, string $type): Collection
    {
        return $this->model->where('user_id', $userId)
            ->where('prediction_type', $type)
            ->get();
    }

    public function getByDateRange(int $userId, string $startDate, string $endDate): Collection
    {
        return $this->model->where('user_id', $userId)
            ->whereBetween('prediction_date', [$startDate, $endDate])
            ->orderBy('prediction_date')
            ->get();
    }
}
