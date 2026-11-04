<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Put on a model whose changes by staff belong in the track record. The model lists the fields worth recording in
 * auditFields(); anything else (and anything secret, see AuditLog) never reaches the log. Only changes made while a staff
 * member is signed in are written, so a member buying a plan or the system running a job leaves no staff line.
 *
 * @method array<int, string> auditFields()
 */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn (Model $m) => AuditLog::modelChanged($m, 'created'));
        static::updated(fn (Model $m) => AuditLog::modelChanged($m, 'updated'));
        static::deleted(fn (Model $m) => AuditLog::modelChanged($m, 'deleted'));
    }

    /** "Plan #3 Gold": what the line is about. */
    public function auditSubject(): string
    {
        $label = $this->auditLabel();

        return class_basename($this).' #'.$this->getKey().($label ? ' '.$label : '');
    }

    public function auditLabel(): ?string
    {
        return $this->getAttribute('name');
    }
}
