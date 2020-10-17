@if( isset($row->details->image_description) )
<br/>
<h5><i>{{ $row->details->image_description }}</i></h5>
@endif
@if(isset($item->{$row->field}))
    <img src="@if( !filter_var($item->{$row->field}, FILTER_VALIDATE_URL)){{ \TCG\Voyager\Facades\Voyager::image( $item->{$row->field} ) }}@else{{ $item->{$row->field} }}@endif"
         style="max-width:200px; height:auto; clear:both; display:block; padding:2px; border:1px solid #ddd; margin-bottom:10px;">
@endif
<div class="container-crop-image-field">

    <div class="formfield-crop-container" style="display: none;width: {{ $imageWidth*2 }}px;height: {{ $imageHeight*2 }}px;"></div>
    <div class="wrap-picker-crop-image">
        <input id="{{ 'icrop_'.$row->field }}" class="" style="display: block;width: 0; height: 0;padding: 0;margin: 0;border: 0;" type="file" name="{{ $row->field }}"<?php

        echo $row->required == 1 && !isset($item->{$row->field}) ? ' required' : '';
        echo $accept ? ' accept="'.$accept.'"' : '';
        ?>/>
        <input class="el-input-store" type="hidden" name="{{ $row->field }}" value="{{ $item->{$row->field} ? $item->{$row->field} : '' }}" data-originval="{{ $item->{$row->field} ? $item->{$row->field} : '' }}" />
        <a href="#{{ 'icrop_'.$row->field }}" data-pfolder="{{ $public_path }}" data-imageheight="{{ $imageHeight }}" data-imagewidth="{{ $imageWidth }}" data-ajaxurl="{{ route('crop.image.upload') }}" data-preview="{{ '#preview_name_icrop_'.$row->field }}" class="btn btn-primary input-file-crop-image">@lang('cropimage::general.choose_image')</a>

        
        <span class="wrap-cropping-action" style="display: none;">
            <a href="#" data-cropurl="{{ route('crop.image') }}" class="btn btn-success btn-run-crop">@lang('cropimage::general.cut_image')</a>
            <label class="wrap-gif">
                <input type="checkbox" name="is_gif" value="" class="check-save-gif" />
                <a href="#">@lang('cropimage::general.is_gif')</a>
            </label>
        </span>
        <span id="{{ 'preview_name_icrop_'.$row->field }}" class="preview-name"></span>
    </div>
</div>
