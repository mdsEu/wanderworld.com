<?php

Route::middleware(['web'])->group(function () {
    Route::group(['prefix' => 'cropimage'], function () {
        Route::post('/crop_upload_image','\MDS\Fields\ImagePreCrop\Controller@uploadImage')->name('crop.image.upload');
        Route::post('/crop_image','\MDS\Fields\ImagePreCrop\Controller@cropImage')->name('crop.image');
    });
});
