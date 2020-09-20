<?php
if( $view == 'browse' ) {
    $dataTypeContent = $data;
}
\MDS\Fields\ImagePreCrop\FormField::myRender(
    $row, $dataType, $dataTypeContent, $options,
    $view, $action
); ?>
