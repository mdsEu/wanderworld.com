<?php
$prefixO = 'export-bread';
$prefix = $prefixO;
if( class_exists('TCG\Voyager\Facades\Voyager') ) {
    $prefix = 'admin/'.$prefix;
}
Route::middleware(['web'])->group(function () use ($prefix) {
    Route::group(['prefix' => $prefix], function () {
        Route::get('/','\Manuel90\ExportBread\Http\Controller@index')->name('exportbread.index');

        Route::get('/assets','\Manuel90\ExportBread\Http\Controller@assets')->name('exportbread.assets');
        Route::get('/eb_generated_report.csv','\Manuel90\ExportBread\Http\Controller@download')->name('exportbread.download');

        Route::group(['prefix' => 'ajax/v1'], function () {
            Route::post('/store_setting','\Manuel90\ExportBread\Http\Controller@saveGeneralSetting')->name('exportbread.store_custom_setting');

           Route::post('/export','\Manuel90\ExportBread\Http\Controller@exportData')->name('exportbread.exportdata');

            Route::get('/data-model','\Manuel90\ExportBread\Http\Controller@dataModel')->name('exportbread.datamodel');

        });
    });
});