<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Toporia\Framework\Support\Collection\Collection;


/**
 * Class ModelCollection
 *
 * Core class for the ORM layer providing essential functionality for the
 * Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  ORM
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class ModelCollection extends Collection implements \JsonSerializable
{
  /**
   * Return the array of primary keys for all models in the collection.
   *
   * @return array<int, int|string>
   */
  public function modelKeys(): array
  {
    return $this->map(fn(Model $m) => $m->getKey())->values()->all();
  }

  /**
   * Find the first model with a matching primary key.
   *
   * @param int|string $key
   * @return Model|null
   */
  public function find(int|string $key): ?Model
  {
    foreach ($this->all() as $m) {
      if ($m instanceof Model && $m->getKey() === $key) {
        return $m;
      }
    }
    return null;
  }

  /**
   * Save all models in the collection (if they implement ->save()).
   *
   * @return int Number of successful saves.
   */
  public function save(): int
  {
    $ok = 0;
    foreach ($this->all() as $m) {
      if (method_exists($m, 'save') && $m->save()) $ok++;
    }
    return $ok;
  }

  /**
   * Convert the collection to an array of model arrays.
   *
   * @return array<int, array<string,mixed>>
   */
  public function toArray(): array
  {
    return $this->map(
      fn(Model $m) => method_exists($m, 'toArray') ? $m->toArray() : get_object_vars($m)
    )->values()->all();
  }

  /**
   * Convert the collection to an array for JSON serialization.
   *
   * Laravel compatibility: implements JsonSerializable interface.
   *
   * @return array
   */
  public function jsonSerialize(): array
  {
    return $this->toArray();
  }
}
