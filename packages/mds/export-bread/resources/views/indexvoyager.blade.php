@extends('voyager::master')
@section('css')
<link rel="stylesheet" href="{{route('exportbread.assets')}}?path=styles.css" />
@stop

@section('content')
    <div id="wrap-second-button">
        <a href="#export" class="export-button btn btn-primary">@lang('exportbread::general.export_selection')</a>
    </div>
    <div class="page-content">
        @include('voyager::alerts')
        @include('voyager::dimmers')
        <h1 class="page-title"><i class="voyager-treasure-open"></i> Export BREADS</h1>
        <div class="page-content edit-add container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="panel panel-bordered">
                        <div class="panel-body">
                            @if($listDataTypes->count() === 1)
                                @php
                                    $dataType = $listDataTypes->first();
                                @endphp
                                <div>
                                    <b>@lang('exportbread::general.data_from')</b>
                                    <h4>{{$dataType->display_name_plural}}</h4>
                                </div>
                                <input name="model_selection_export" value="{{$dataType->id}}" type="hidden" />
                            @else
                                <div>
                                    <b>@lang('exportbread::general.data_from')</b>
                                    <select name="model_selection_export" class="select2">
                                        <option value="">@lang('exportbread::general.select_an_option')</option>
                                        @foreach($listDataTypes as $dataType)
                                        <option value="{{$dataType->id}}">{{$dataType->display_name_plural}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <hr/>
                            <a id="first-button-export" href="#export" class="export-button btn btn-primary">@lang('exportbread::general.export_selection')</a>
                            <div id="selection-columns"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script type="text/javascript">

        $(window).scroll(function() {

            var $navBar = $('.navbar.navbar-fixed-top.navbar-top').first();
            var $appContainer = $('.app-container .content-container').first();
            var $firstBtn = $('#first-button-export');
            
            var scroll = $(window).scrollTop();

            if($firstBtn.offset().top - $navBar.height() < scroll) {
                $appContainer.addClass('fix-export-second-button');
            } else {
                $appContainer.removeClass('fix-export-second-button');
            }

        });

        $('document').ready(function () {

            var $navBar = $('.navbar.navbar-fixed-top.navbar-top').first();
            var $wrapSecondButton = $('#wrap-second-button');
            $wrapSecondButton.css('top', $navBar.height());



            var $panelSelection = $('#selection-columns');
            $panelSelection.data('selection', {});

            var $loader = jQuery('#voyager-loader');
            var $selModel = $('[name="model_selection_export"]').first();
            
            var onChangeCheckbox = function(e) {
                var $checkbox = $(e.currentTarget);
                

                var schema = $panelSelection.data('schema');

                if($checkbox.prop('checked')) {

                    $checkbox.parent().addClass('checked');
                    addSelection($checkbox.val(), $checkbox.data('parents') ? $checkbox.data('parents').split(',') : []);
                    
                } else {
                    $checkbox.parent().removeClass('checked');
                    $checkbox.parent().parent().find('input[type="checkbox"]').prop('checked', false);
                    removeSelection($checkbox.val(), $checkbox.data('parents') ? $checkbox.data('parents').split(',') : []);

                }
            };

            $( document.body ).on( 'change', 'input[type="checkbox"]', onChangeCheckbox );
            $( document.body ).on( 'click', '#export-bread-check-all label', function(e) {
                var $this = $(e.currentTarget);
                $this.toggleClass('checked');

                var $listCheckbox = $panelSelection.find('input[type="checkbox"]');

                $listCheckbox.each(function(idx, checkbox) {
                    var $checkbox = $(checkbox);
                    $checkbox.attr('checked',$this.hasClass('checked'));

                    if ($this.hasClass('checked')) {
                        $checkbox.parent().addClass('checked');                        
                    } else {
                        $checkbox.parent().removeClass('checked');                        
                    }
                });

                $listCheckbox.each(function(idx, checkbox) {
                    var $checkbox = $(checkbox);
                    if ($this.hasClass('checked')) {
                        addSelection($checkbox.val(), $checkbox.data('parents') ? $checkbox.data('parents').split(',') : []);
                    } else {
                        removeSelection($checkbox.val(), $checkbox.data('parents') ? $checkbox.data('parents').split(',') : []);
                    }
                });
            } );
            
            

            var addSelection = function(column, parents) {
                var bundle = $panelSelection.data('selection');

                if(parents.length === 0) {
                    bundle[column] = {};
                } else {
                    var obj = bundle;
                    parents.forEach(function(pColumn) {
                        if(!obj[pColumn]) {
                            throw new Error("Imposible... but, It was");
                        }
                        obj = obj[pColumn];
                    });
                    obj[column] = {};
                }

                $panelSelection.data('selection', bundle);
            };

            var removeSelection = function(column, parents) {
                var bundle = $panelSelection.data('selection');

                if(parents.length === 0) {
                    delete bundle[column];
                } else {
                    var obj = bundle;
                    parents.forEach(function(pColumn) {
                        if(obj[pColumn]) {
                            obj = obj[pColumn];
                        }
                    });
                    delete obj[column];
                }

                $panelSelection.data('selection', bundle);
            };

            var createRows = function(rows, container, level = 1, parentsColumn = []) {
                rows.forEach(function(el,idx) {

                    var tplRow = `
                            <label><input type="checkbox" name="check_${el.column}_${parentsColumn.length}_${level}" value="${el.column}" data-level="${level}" data-parents="${parentsColumn.join(',')}" /> ${el.label}</label>
                    `;
                    var $row = $('<div class="row-selection">');
                    $row.append(tplRow);

                    container.append($row);

                    if (el.subschema instanceof Array) {
                        var clone = JSON.parse(JSON.stringify(parentsColumn));
                        clone.push(el.column);
                        createRows(el.subschema, $row, level + 1, clone);
                    }

                });
            };

            var buildUISelectionSchema = function(schema) {
                $panelSelection.data('selection', {});
                $panelSelection.data('schema', schema);
                var rows = schema;
                var container = $panelSelection;
                var level = 1;
                var parentsColumn = [];

                rows.forEach(function(el,idx) {

                    var tplRow = `
                            <label><input type="checkbox" name="check_${el.column}_${parentsColumn.length}_${level}" value="${el.column}" data-level="${level}" data-rootidx="${idx}" data-parents="${parentsColumn.join(',')}" /> ${el.label}</label>
                    `;
                    var $row = $('<div class="row-selection">');
                    $row.append(tplRow);

                    container.append($row);

                    if (el.subschema instanceof Array) {
                        var clone = JSON.parse(JSON.stringify(parentsColumn));
                        clone.push(el.column);
                        createRows(el.subschema, $row, level + 1, clone);
                    }

                });

            };

            var fail = function(error,hrxr) {
                var id_panel = 'lfail_bg_mzra_panel51441';

                console.log(error);
                console.log(error.status);
                console.log(error.statusText);
                console.log(hrxr);

                var options = {
                    seconds: 10,
                    text: 'Connection is lost. Try connect in {t} Message: '+error.statusText,
                };

                var args = {};

                for(var i in options) {
                    if( !args[i] ) {
                        args[i] = options[i];
                    }
                }

                if( document.getElementById(id_panel) ) {
                    return;
                }

                var element = document.createElement('div');
                element.setAttribute('id', id_panel);
                element.style.display = 'block';
                element.style.position = 'fixed';
                element.style.left = '0';
                element.style.top = '0';
                element.style.width = '100%';
                element.style.height = '100%';
                element.style.background = 'rgba(255,255,255,0.5)';
                element.style.zIndex = '9999';
                element.innerHTML = '<span style="color: #fff;position: absolute;left: 50%;top: 50px;transform: translate(-50%,-50%);font-style: italic;font-size: 18px;font-weight: bold;background: rgba(0,0,0,0.9);border-radius:20px;padding: 20px 40px 20px 30px;"><span id="text-lost_1014">'+args.text.replace("{t}",args.seconds)+'</span><i id="dots_1014" style="text-decoration: none;position: absolute;"></i></span>';

                document.body.appendChild(element);

                document.getElementById('text-lost_1014').data_time = args.seconds;

                window.dotsTAnimation = window.setInterval(function(){
                    var e = document.getElementById('text-lost_1014');
                    var t = parseInt(e.data_time);
                    if(t < 0) {
                        return;
                    }
                    document.getElementById('text-lost_1014').innerHTML = args.text.replace(/{t}/g,t);
                    t--;
                    document.getElementById('text-lost_1014').data_time = t;
                    if(t < 0 && window.dotsTAnimation) {
                        clearInterval(window.dotsTAnimation);
                        window.location.reload();
                    }
                },1000);

            };


            $selModel.on('change', function(e) {
                e.preventDefault();
                var _this = $(this);
                if( _this.data('changing') || isNaN(parseInt($selModel.val()))) {
                    return;
                }
                _this.data('changing',true);
                $loader.show();

                $panelSelection.html(`
                <div id="export-bread-check-all">
                    <label class="lbl-check-all"><span class="icon-check"><i class="voyager-check"></i></span> @lang('exportbread::general.select_all')</label>
                    <br/>
                </div>
                `);

                $.ajax({
                    url: '{{ route('exportbread.datamodel') }}',
                    type: 'get',
                    dataType: 'json',
                    data: {
                        ajax: 'yes',
                        data_type_id: parseInt($selModel.val()),
                        _token: '{{ csrf_token() }}',
                    }
                }).done(function(result){
                    $loader.fadeOut();
                    _this.data('changing',false);
                    if( result.success ) {
                        buildUISelectionSchema(result.data.schema);
                    } else {
                        toastr.error(result.message);
                    }
                }).fail(fail);
            });

            $selModel.trigger('change');

            $('a.export-button').on('click', function(e) {
                e.preventDefault();
                var bundle = $panelSelection.data('selection');
                if (Object.keys(bundle).length === 0) {
                    toastr.error("@lang('exportbread::general.no_selection')");
                    return;
                }

                if( $panelSelection.data('exporting') ) {
                    return;
                }
                $panelSelection.data('exporting',true);
                $loader.show();

                $.ajax({
                    url: '{{ route('exportbread.exportdata') }}',
                    type: 'post',
                    dataType: 'json',
                    data: {
                        ajax: 'yes',
                        data_type_id: parseInt($selModel.val()),
                        schema_export: JSON.stringify(bundle),
                        _token: '{{ csrf_token() }}',
                    }
                }).done(function(result){
                    $loader.fadeOut();
                    if( result.success ) {
                        toastr.success(result.message);
                        setTimeout(function() {
                            $panelSelection.data('exporting',false);
                            window.open("{{route('exportbread.download')}}", '_blank');
                        }, 2000);
                    } else {
                        $panelSelection.data('exporting',false);
                        toastr.error(result.message);
                    }
                }).fail(fail);
            });
            
        });
    </script>
@stop