<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LaravelFunLab\Traits\Awardable;

/**
 * A GitHub contributor we track and reward.
 *
 * There are no site accounts or registration — contributors are created on the
 * fly from GitHub webhook payloads and XP is tracked directly against their
 * GitHub identity.
 */
class Contributor extends Model
{
    use Awardable;

    protected $fillable = ['github_id', 'github_username', 'avatar_url', 'name'];
}
