<?php

namespace LaraSlice\Core\Audit\Traits;

use LaraSlice\Core\Audit\AuditLogger;

/**
 * Trait AuditableSlice
 *
 * Plug-and-play Eloquent model auditing for LaraSlice.
 * Automatically records created, updated, and deleted events with granular field diffs.
 */
trait AuditableSlice
{
    /**
     * Whether auditing is temporarily disabled for this model instance.
     */
    protected bool $auditDisabled = false;

    /**
     * Boot the AuditableSlice trait for the model.
     */
    public static function bootAuditableSlice(): void
    {
        static::created(function ($model) {
            if (! $model->shouldAudit('created')) {
                return;
            }

            $newValues = $model->filterAuditAttributes($model->getAttributes());

            AuditLogger::record([
                'slice'       => $model->getAuditSlice(),
                'action'      => 'created',
                'entity_type' => get_class($model),
                'entity_id'   => $model->getKey(),
                'old_values'  => null,
                'new_values'  => $newValues,
                'metadata'    => $model->getAuditMetadata('created'),
            ]);
        });

        static::updated(function ($model) {
            if (! $model->shouldAudit('updated')) {
                return;
            }

            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changes as $key => $newValue) {
                if ($model->isAuditExcluded($key)) {
                    continue;
                }

                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            if (empty($newValues)) {
                return;
            }

            AuditLogger::record([
                'slice'       => $model->getAuditSlice(),
                'action'      => 'updated',
                'entity_type' => get_class($model),
                'entity_id'   => $model->getKey(),
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                'metadata'    => $model->getAuditMetadata('updated'),
            ]);
        });

        static::deleted(function ($model) {
            if (! $model->shouldAudit('deleted')) {
                return;
            }

            $rawAttributes = method_exists($model, 'getOriginal') && !empty($model->getOriginal())
                ? $model->getOriginal()
                : $model->getAttributes();

            $oldValues = $model->filterAuditAttributes($rawAttributes);

            AuditLogger::record([
                'slice'       => $model->getAuditSlice(),
                'action'      => 'deleted',
                'entity_type' => get_class($model),
                'entity_id'   => $model->getKey(),
                'old_values'  => $oldValues,
                'new_values'  => null,
                'metadata'    => $model->getAuditMetadata('deleted'),
            ]);
        });
    }

    /**
     * Execute a callback with auditing temporarily disabled.
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $previous = $this->auditDisabled;
        $this->auditDisabled = true;

        try {
            return $callback($this);
        } finally {
            $this->auditDisabled = $previous;
        }
    }

    /**
     * Determine if the action should be audited.
     */
    public function shouldAudit(string $action): bool
    {
        if ($this->auditDisabled) {
            return false;
        }

        if (isset($this->auditEnabled) && ! $this->auditEnabled) {
            return false;
        }

        return true;
    }

    /**
     * Determine the slice name for this model.
     */
    public function getAuditSlice(): string
    {
        if (isset($this->auditSlice) && !empty($this->auditSlice)) {
            return (string) $this->auditSlice;
        }

        $className = get_class($this);

        // Pattern: App\Slices\{SliceName}\... or LaraSlice\Slices\{SliceName}\...
        if (preg_match('/Slices\\\\([^\\\\]+)/', $className, $matches)) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $matches[1]));
        }

        if (method_exists($this, 'getTable')) {
            return $this->getTable();
        }

        return 'global';
    }

    /**
     * Filter out excluded or sensitive attributes.
     */
    public function filterAuditAttributes(array $attributes): array
    {
        $filtered = [];
        foreach ($attributes as $key => $value) {
            if (! $this->isAuditExcluded($key)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    /**
     * Check if a specific attribute is excluded from auditing.
     */
    public function isAuditExcluded(string $key): bool
    {
        $defaults = [
            'password',
            'remember_token',
            'api_token',
            'token',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'updated_at',
        ];

        if (in_array($key, $defaults, true)) {
            return true;
        }

        if (isset($this->auditExclude) && is_array($this->auditExclude)) {
            if (in_array($key, $this->auditExclude, true)) {
                return true;
            }
        }

        if (method_exists($this, 'getHidden') && in_array($key, $this->getHidden(), true)) {
            return true;
        }

        return false;
    }

    /**
     * Get optional metadata to record with the audit entry.
     */
    public function getAuditMetadata(string $action): ?array
    {
        return null;
    }

    /**
     * Query audit logs for this specific entity instance.
     */
    public function getAuditTrail(int $limit = 50)
    {
        return AuditLogger::forEntity(get_class($this), $this->getKey(), $limit);
    }
}
