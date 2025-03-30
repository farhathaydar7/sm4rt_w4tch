<?php

namespace App\Repositories\Interfaces;

use App\Models\CsvUpload;
use Illuminate\Database\Eloquent\Collection;

interface CsvUploadRepositoryInterface
{
    /**
     * Get all CSV uploads
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find a CSV upload by ID
     *
     * @param int $id
     * @return CsvUpload|null
     */
    public function find(int $id): ?CsvUpload;

    /**
     * Create a new CSV upload
     *
     * @param array $data
     * @return CsvUpload
     */
    public function create(array $data): CsvUpload;

    /**
     * Update a CSV upload
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a CSV upload
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get all CSV uploads for a user
     *
     * @param int $userId
     * @return Collection
     */
    public function getByUserId(int $userId): Collection;

    /**
     * Get a CSV upload with its related activity metrics
     *
     * @param int $id
     * @return CsvUpload|null
     */
    public function getWithActivityMetrics(int $id): ?CsvUpload;
}
