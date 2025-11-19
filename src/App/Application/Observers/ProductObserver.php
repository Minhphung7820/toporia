<?php

declare(strict_types=1);

namespace App\Application\Observers;

use App\Domain\Product;

/**
 * Product Observer
 *
 * Handles lifecycle events for the Product model.
 * Each method receives the model instance and can modify it directly.
 *
 * Available events:
 * - creating: Before a new model is saved
 * - created: After a new model is saved
 * - updating: Before an existing model is saved
 * - updated: After an existing model is saved
 * - saving: Before any save (create or update)
 * - saved: After any save (create or update)
 * - deleting: Before a model is deleted
 * - deleted: After a model is deleted
 * - restoring: Before a soft-deleted model is restored
 * - restored: After a soft-deleted model is restored
 * - forceDeleting: Before a model is force deleted
 * - forceDeleted: After a model is force deleted
 * - retrieved: After a model is retrieved from database
 *
 * Return false from "ing" events to cancel the operation.
 */
final class ProductObserver
{
    /**
     * Handle the Product "creating" event.
     * Return false to cancel the creation.
     */
    public function creating(Product $product): bool
    {
        return true;
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "updating" event.
     * Return false to cancel the update.
     */
    public function updating(Product $product): bool
    {
        return true;
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "saving" event.
     * Return false to cancel the save.
     */
    public function saving(Product $product): bool
    {
        return true;
    }

    /**
     * Handle the Product "saved" event.
     */
    public function saved(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "deleting" event.
     * Return false to cancel the deletion.
     */
    public function deleting(Product $product): bool
    {
        return true;
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "forceDeleting" event.
     * Return false to cancel the force deletion.
     */
    public function forceDeleting(Product $product): bool
    {
        return true;
    }

    /**
     * Handle the Product "forceDeleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "retrieved" event.
     */
    public function retrieved(Product $product): void
    {
        //
    }
}
