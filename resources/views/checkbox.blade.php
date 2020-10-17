
@if( $view == 'browse' )

    {{ $data->{$row->field} ? __('On') : __('Off')  }}

@else
    {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}
@endif
