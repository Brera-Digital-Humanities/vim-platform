<?php namespace Quivi\Archive\Models;

use Model;

/**
 * Model
 */
class MetaValue extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    
    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'quivi_archive_meta_values';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $belongsTo = [
        'meta_key' => [MetaKey::class, 'key' => 'meta_key_id']
    ];

    public function scopeLang($query, $lang)
    {
        $query->where('lang', $lang);
    }
}
