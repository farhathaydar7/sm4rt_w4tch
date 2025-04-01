<?php

namespace App\Repositories;

use App\Models\CsvUpload;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CsvUploadRepository implements CsvUploadRepositoryInterface
{
    protected $model;

    public function __construct(CsvUpload $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(int $id): ?CsvUpload
    {
        return $this->model->find($id);
    }

    public function create(array $data): CsvUpload
    {
        return $this->model->create($data);
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

    public function getWithActivityMetrics(int $id): ?CsvUpload
    {
        return $this->model->with('activityMetrics')->find($id);
    }
}
