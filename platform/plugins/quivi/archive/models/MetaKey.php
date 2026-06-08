<?php namespace Quivi\Archive\Models;

use Model;

/**
 * Model
 */
class MetaKey extends Model
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
    public $table = 'quivi_archive_meta_keys';

    /**
     * @var array Validation rules
     */
    public $rules = [
    ];

    public $hasMany = [
        'meta_values' => [MetaValue::class, 'key' => 'meta_key_id']
    ];

    public function scopeLang($query, $lang)
    {
        $query->whereHas('meta_values', function ($q) use ($lang) {
            $q->where('lang', $lang);
        })->with(['meta_values' => function ($q) use ($lang) {
            $q->lang($lang);
        }]);
    }
}
