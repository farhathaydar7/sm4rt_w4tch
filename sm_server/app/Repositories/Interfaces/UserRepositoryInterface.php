<?php

namespace App\Repositories\Interfaces;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Get all users
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Find a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User;

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Update a user
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a user
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User;

    /**
     * Get a user with their activity metrics
     *
     * @param int $id
     * @return User|null
     */
    public function getUserWithMetrics(int $id): ?User;
}
