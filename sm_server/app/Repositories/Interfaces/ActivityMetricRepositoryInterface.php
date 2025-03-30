<?php

namespace App\Repositories\Interfaces;

use App\Models\ActivityMetric;
use Illuminate\Database\Eloquent\Collection;

interface ActivityMetricRepositoryInterface
{
    /**
     * Get all activity metrics
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find an activity metric by ID
     *
     * @param int $id
     * @return ActivityMetric|null
     */
    public function find(int $id): ?ActivityMetric;

    /**
     * Create a new activity metric
     *
     * @param array $data
     * @return ActivityMetric
     */
    public function create(array $data): ActivityMetric;

    /**
     * Create multiple activity metrics
     *
     * @param array $data
     * @return bool
     */
    public function createMany(array $data): bool;

    /**
     * Update an activity metric
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete an activity metric
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get all activity metrics for a user
     *
     * @param int $userId
     * @return Collection
     */
    public function getByUserId(int $userId): Collection;

    /**
     * Get all activity metrics for a CSV upload
     *
     * @param int $csvUploadId
     * @return Collection
     */
    public function getByCsvUploadId(int $csvUploadId): Collection;

    /**
     * Get activity metrics for a date range
     *
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(int $userId, string $startDate, string $endDate): Collection;
}
