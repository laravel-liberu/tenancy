<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Database\Concerns;

use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Events\PullingPendingTenant;
use Stancl\Tenancy\Events\PendingTenantPulled;
use Stancl\Tenancy\Events\CreatingPendingTenant;
use Stancl\Tenancy\Events\PendingTenantCreated;

/**
 * @property $pending_since
 *
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withPending(bool $withPending = true)
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder onlyPending()
 * @method static static|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder withoutPending()
 */
trait HasPending
{
    /**
     * Boot the has pending trait for a model.
     *
     * @return void
     */
    public static function bootHasPending()
    {
        static::addGlobalScope(new PendingScope());
    }

    /**
     * Initialize the has pending trait for an instance.
     *
     * @return void
     */
    public function initializeHasPending()
    {
        $this->casts['pending_since'] = 'timestamp';
    }


    /**
     * Determine if the model instance is in a pending state.
     *
     * @return bool
     */
    public function pending()
    {
        return !is_null($this->pending_since);
    }

    public static function createPending($attributes = []): void
    {
        $tenant = static::create($attributes);

        event(new CreatingPendingTenant($tenant));

        // We add the pending value only after the model has then been created.
        // this ensures the model is not marked as pending until the migrations, seeders, etc. are done
        $tenant->update([
            'pending_since' => now()->timestamp
        ]);

        event(new PendingTenantCreated($tenant));
    }

    public static function pullPendingTenant(bool $firstOrCreate = false): ?Tenant
    {
        if (!static::onlyPending()->exists()) {
            if (!$firstOrCreate) {
                return null;
            }
            static::createPending();
        }

        // At this point we can guarantee a pending tenant is free and can be called.
        $tenant = static::onlyPending()->first();

        event(new PullingPendingTenant($tenant));

        $tenant->update([
            'pending_since' => null
        ]);

        event(new PendingTenantPulled($tenant));

        return $tenant;
    }
}