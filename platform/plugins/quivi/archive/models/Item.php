<?php namespace Quivi\Archive\Models;

use Model;

/**
 * Model
 */
class Item extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    
    use \Winter\Storm\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'quivi_archive_items';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $attachMany = [
        'files' => ['System\Models\File']
    ];

    public $jsonable = [
        'data'
    ];

    // Possible development: 
    // use a polymorphic relation to link to submissions from 
    // different sources (e.g. Kobo, ODK, etc.)
    public $belongsTo = [
        'submission' => [\Quivi\Kobo\Models\Submission::class, 'key' => 'submission_id']
    ];
    
}
