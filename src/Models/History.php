<?php

namespace JeromeSavin\UccelloHistory\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Uccello\Core\Database\Eloquent\Model;
use Uccello\Core\Support\Traits\UccelloModule;

class History extends Model
{
    use SoftDeletes, UccelloModule;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'histories';

    protected $fillable = ['model_uuid', 'actor_id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
 
    /**
    * Returns record label
    *
    * @return string
    */
    public function getRecordLabelAttribute() : string
    {
        return $this->id;
    }
}
