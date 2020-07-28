
@if( $view == 'browse' )

    {{ $data->{$row->field} ? __('On') : 'Off'  }}

@else
    {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}
@endif
