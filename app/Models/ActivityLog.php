<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Audit log entries are immutable and cannot be modified.');
        }

        return parent::save($options);
    }

    public function delete(): bool|null
    {
        throw new \LogicException('Audit log entries are immutable and cannot be deleted.');
    }
}
