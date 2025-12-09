<?php

namespace OBSTechnologies\InvoiceAI\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenant
{
    public static function bootHasTenant(): void
    {
        if (!config('invoiceai.multi_tenancy.enabled', true)) {
            return;
        }

        // Add global scope to filter by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::resolveTenantId();

            if ($tenantId !== null) {
                $builder->where(
                    config('invoiceai.multi_tenancy.column', 'company_id'),
                    $tenantId
                );
            }
        });

        // Auto-assign tenant on creating
        static::creating(function ($model) {
            $tenantColumn = config('invoiceai.multi_tenancy.column', 'company_id');

            if (empty($model->{$tenantColumn})) {
                $tenantId = static::resolveTenantId();

                if ($tenantId !== null) {
                    $model->{$tenantColumn} = $tenantId;
                }
            }
        });
    }

    /**
     * Resolve the current tenant ID.
     */
    protected static function resolveTenantId(): ?int
    {
        $resolver = config('invoiceai.multi_tenancy.resolver');

        if ($resolver !== null) {
            if (is_callable($resolver)) {
                return $resolver();
            }

            if (is_string($resolver) && class_exists($resolver)) {
                return app($resolver)->resolve();
            }
        }

        // Default: try to get from authenticated user
        if (auth()->check()) {
            $user = auth()->user();
            $column = config('invoiceai.multi_tenancy.column', 'company_id');

            if (isset($user->{$column})) {
                return $user->{$column};
            }
        }

        // Try container binding
        if (app()->bound('invoiceai.tenant_id')) {
            return app('invoiceai.tenant_id');
        }

        return null;
    }

    /**
     * Query without tenant scope.
     */
    public static function withoutTenant(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
