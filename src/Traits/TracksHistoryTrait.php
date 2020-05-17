<?php

namespace JeromeSavin\UccelloHistory\Traits;

use Illuminate\Support\Facades\Auth;
use JeromeSavin\UccelloHistory\Models\History;
use Uccello\Core\Database\Eloquent\Model;
use Uccello\Core\Models\Entity;

trait TracksHistoryTrait
{

    public function histories()
    {
        return $this->hasManyThrough(History::class, Entity::class, 'record_id', 'model_uuid', 'id', 'id')
            ->orderBy('created_at', 'desc');
    }

    protected function track(Model $model, callable $func = null, $table = null, $id = null)
    {
        // Allow for overriding of table if it's not the model table
        $table = $table ?: $model->getTable();
        // Allow for overriding of id if it's not the model id
        $id = $id ?: $model->id;
        // Allow for customization of the history record if needed
        $func = $func ?: [$this, 'getHistoryBody'];

        // Get the dirty fields and run them through the custom function, then insert them into the history table
        $this->getUpdated($model)
             ->map(function ($value, $field) use ($func, $model) {
                return call_user_func_array($func, [$value, $field, $model]);
             })
             ->each(function ($fields) use ($table, $id, $model) {

                $history = new History;
                $history->model_uuid = $model->uuid;
                $history->user_id = Auth::user()->id;
                $history->domain_id = $model->domain_id;

                foreach ($fields as $key => $field) {
                    $history->$key = $field;
                }

                $history->save();
             });
    }

    protected function getHistoryBody($value, $field, $model)
    {
        return [
            'description' => "Le champ '".uctrans('field.'.$field, $model->module)."' a été changé de '".$model->getOriginal($field)."' en '${value}'",
        ];
    }

    protected function getUpdated($model)
    {
        return collect($model->getDirty())->filter(function ($value, $key) {
            // We don't care if timestamps are dirty, we're not tracking those
            return !in_array($key, ['created_at', 'updated_at']);
        })->mapWithKeys(function ($value, $key) {
            // Take the field names and convert them into human readable strings for the description of the action
            // e.g. first_name -> first name
            return [str_replace('_', ' ', $key) => $value];
        });
    }

    public static function bootTracksHistoryTrait()
    {
        static::updating(function ($model) {
            $model->track($model);
        });
    }
}
