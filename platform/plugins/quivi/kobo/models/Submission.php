<?php namespace Quivi\Kobo\Models;

use Model;

/**
 * Model
 */
class Submission extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public const STATUS_CREATED = 'created';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'quivi_kobo_submissions';

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'user_id',
        'asset_uid',
        'kobo_id',
        'kobo_uuid',
        'status',
        'error',
    ];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user' => [\Winter\User\Models\User::class],
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];
}
