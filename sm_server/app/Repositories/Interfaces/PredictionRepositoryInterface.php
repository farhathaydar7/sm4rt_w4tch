<?php

namespace App\Repositories\Interfaces;

use App\Models\Prediction;
use Illuminate\Database\Eloquent\Collection;

interface PredictionRepositoryInterface
{
    /**
     * Get all predictions
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find a prediction by ID
     *
     * @param int $id
     * @return Prediction|null
     */
    public function find(int $id): ?Prediction;

    /**
     * Create a new prediction
     *
     * @param array $data
     * @return Prediction
     */
    public function create(array $data): Prediction;

    /**
     * Create multiple predictions
     *
     * @param array $data
     * @return bool
     */
    public function createMany(array $data): bool;

    /**
     * Update a prediction
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a prediction
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get all predictions for a user
     *
     * @param int $userId
     * @return Collection
     */
    public function getByUserId(int $userId): Collection;

    /**
     * Get all predictions for a CSV upload
     *
     * @param int $csvUploadId
     * @return Collection
     */
    public function getByCsvUploadId(int $csvUploadId): Collection;

    /**
     * Get predictions of a specific type
     *
     * @param int $userId
     * @param string $type
     * @return Collection
     */
    public function getByType(int $userId, string $type): Collection;

    /**
     * Get predictions for a date range
     *
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(int $userId, string $startDate, string $endDate): Collection;
}
