<?php

namespace Manuel90\ExportBread;

use Illuminate\Support\Facades\DB;

class ExportBread {
    public static function getListDataTypes($only = null) {
        

        $onlyDefault = !is_array($only) ? [
            'App\Models\AppUser',
        ] : $only;
        $collection = DB::table('data_types')->select('*')->whereIn('model_name', $onlyDefault)->get();


        return $collection;
    }

    public static function getDataType($id) {
        return DB::table('data_types')->select('*')->where('id', $id)->first();
    }


    public static function getTableColumns($model) {
        return $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
    }

    public static function jsonSchemaColumns($model, $deep = 1, $only = []) {
        if( !method_exists(get_class($model), 'exportColumnsName') ) {
            throw new ExportBreadException(__('exportbread::general.model_not_prepared', ['name' => get_class($model)]));
        }
        $fore = [];
        if( method_exists(get_class($model), 'exportForeigns') ) {
            $fore = \call_user_func(array(get_class($model),'exportForeigns'));
        }
        
        $columns = \call_user_func(array(get_class($model),'exportColumnsName'));//self::getTableColumns($model);
        $jsonSchema = [];
        foreach($columns as $col=>$label) {
            if(is_array($only) && count($only) > 0 && !in_array($col, $only)) {
                continue;
            }
            if(\array_key_exists($col, $fore) && $deep <= 3) {
                $foreModel = app($fore[$col]['model']);
                $jsonSchema[] = array(
                    'column' => $col,
                    'label' => $label,
                    'class' => $fore[$col]['model'],
                    'subschema' => self::jsonSchemaColumns($foreModel, $deep + 1, $fore[$col]['show'])
                );
                continue;
            }
            $jsonSchema[] = array(
                'column' => $col,
                'label' => $label,
            );

        }
        return $jsonSchema;
    } 
}