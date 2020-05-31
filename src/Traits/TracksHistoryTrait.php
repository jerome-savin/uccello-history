<?php

namespace JeromeSavin\UccelloHistory\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use JeromeSavin\UccelloHistory\Models\History;
use Uccello\Core\Database\Eloquent\Model;
use Uccello\Core\Models\Entity;
use Uccello\Core\Models\Module;
use Illuminate\Support\Str;

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

        if (!$model->uuid) {
            if ($model->module) {
                Entity::create([
                    'id' => (string) Str::uuid(),
                    'module_id' => $model->module->id,
                    'record_id' => $model->getKey(),
                    'creator_id' => auth()->id(),
                ]);
            }
        }

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
            'description' => "Le champ '".uctrans('field.'.$field, $model->module).
                "' a été changé de '".
                TracksHistoryTrait::transIfExists($model->getOriginal($field), $model->module->name).
                "' en '".
                TracksHistoryTrait::transIfExists($value, $model->module->name).
                "'",
        ];
    }

    public static function transIfExists($value, $moduleName)
    {
        return Lang::has($moduleName.'.'.$value)?
            trans($moduleName.'.'.$value):
            $value;
    }

    protected function getUpdated($model)
    {
        $untrackableFields = $this->untrackableFields;
        if (!$untrackableFields) {
            $untrackableFields = [];
        }
        return collect($model->getDirty())->filter(function ($value, $key) use ($untrackableFields) {
            // We don't care if timestamps are dirty, we're not tracking those
            $a_timestamps = ['created_at', 'updated_at'];
            return !in_array($key, array_merge($a_timestamps, $untrackableFields));
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

        static::deleted(function ($model) {
            $model->histories()->delete();
        });

        static::restored(function ($model) {
            $model->histories()->restore();
        });
    }
}
