<?php

namespace Manuel90\ExportBread\Http;

use Illuminate\Http\Request;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Testing\MimeType;

use Illuminate\Support\Facades\Auth;

use Intervention\Image\Facades\Image;

use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\Setting;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Manuel90\ExportBread\ExportBread;
use Manuel90\ExportBread\ExportBreadException;

use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

use App\Http\Controllers\Controller as BaseController;

class Controller extends BaseController
{

    protected $sheet;
    protected $rowIdx;
    protected $colIdx;
    protected $schemaSelected;

    public function __construct() {
        if( class_exists('Voyager') ) {
            $this->middleware('admin.user')->only('index');
        }
    }

    public function index(Request $request) {


        $list = ExportBread::getListDataTypes();
        if( class_exists('Voyager') ) {
            return view('exportbread::indexvoyager',[
                'listDataTypes' => $list,
            ]);
        }

        return view('exportbread::index',['listSettings' => $list]);
    }


    public function exportData(Request $request) {
        try {

            if (!class_exists('PhpOffice\PhpSpreadsheet\Writer\Csv')) {
                throw new ExportBreadException( __('exportbread::general.phpoffice_library_required') );
            }

            if( !auth()->user()->hasPermission('edit_settings') ) {
                throw new ExportBreadException( __('exportbread::general.error_permission') );
            }

            $dataTypeId = $request->get('data_type_id', 0);
            $dataType = ExportBread::getDataType($dataTypeId);
            
            $schemaSelectedStr = $request->get('schema_export', "");

            $this->schemaSelected = \json_decode($schemaSelectedStr, true);

            if(!is_array($this->schemaSelected)) {
                throw new ExportBreadException(__('exportbread::general.no_selection'));
            }

            if(!$dataType) {
                throw new ExportBreadException(__('exportbread::general.model_not_found'));
            }

            $disk = 'local';//config('filesystems.default', 'local');
            $path = config('filesystems.disks.'.$disk, [])['root'];

            if(!is_writeable($path)) {
                throw new ExportBreadException( __('exportbread::general.local_storage_is_not_writeable') );
            }

            $folder = $path."/exports-bread";

            if(!file_exists($folder) && !mkdir($folder, 0775)) {
                throw new ExportBreadException( __('exportbread::general.local_storage_is_not_writeable') );
            }

            $modelInstance = app($dataType->model_name);
            
            $schema = ExportBread::jsonSchemaColumns($modelInstance);


            $spreadsheet = new Spreadsheet();
            $this->sheet = $spreadsheet->getActiveSheet();

            $this->rowIdx = 1;
            /**
             * Csv columns
             */
            $this->colIdx = 'A';
            foreach($schema as $infoModel) {
                if(!\array_key_exists($infoModel['column'], $this->schemaSelected)) {
                    continue;
                }
                $this->sheet->setCellValue("{$this->colIdx}{$this->rowIdx}", $infoModel['label']);
                $this->colIdx++;
                if(isset($infoModel['subschema'])) {
                    $prevParents = [$infoModel['column']];
                    $this->recursiveColumns($infoModel, $prevParents);
                }
            }
            $this->rowIdx++;

            /**
             * Csv Rows
             */
            $this->colIdx = 'A';
            $modelRecords = $modelInstance::all();
            foreach($modelRecords as $modelRow) {
                foreach($schema as $infoModel) {
                    if(!\array_key_exists($infoModel['column'], $this->schemaSelected)) {
                        continue;
                    }
                    $val = empty($modelRow) || empty($modelRow->{$infoModel['column']}) ? "" : $modelRow->{$infoModel['column']};
                    if(method_exists($val,'toString')) {
                        $val = $val->toString();
                    } elseif(is_object($val) || is_array($val)) {
                        $val = var_export($val, true);
                    }
                    $this->sheet->setCellValue("{$this->colIdx}{$this->rowIdx}", $val);
                    $this->colIdx++;
                    if(isset($infoModel['subschema'])) {
                        $prevParents = [$infoModel['column']];
                        $this->recursiveRecords($infoModel, $modelRow, $prevParents);
                    }
                }
                $this->colIdx = 'A';
                $this->rowIdx++;
            }

            $writer = new Csv($spreadsheet);
            $writer->setDelimiter(';');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $writer->setSheetIndex(0);

            $writer->save( "$folder/eb_generated_report.csv" );

            return response()->json([
                'success' => true,
                'message' => __('exportbread::general.export_successfully'),
            ]);
        } catch (ModelNotFoundException $nt) {
            return response()->json([
                'success' => false,
                'message' => __('exportbread::general.model_not_found'),
            ]);
        } catch (ExportBreadException $ebe) {
            return response()->json([
                'success' => false,
                'message' => $ebe->getMessage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),//__('exportbread::general.error_exporting'),
            ]);
        }
    }

    private function recursiveColumns($infoModelParent, $parents = []) {
        $schema = $infoModelParent['subschema'];
        $column = $infoModelParent['column'];
        $parentLabel = $infoModelParent['label'];
        
        $schemaSelected = $this->schemaSelected;
        if(count($parents) > 1) {
            foreach($parents as $c) {
                if($column === $c) {
                    break;
                }
                $schemaSelected = $schemaSelected[$c];
            }
        }

        
        foreach($schema as $infoModel) {
            
            if(!\array_key_exists($infoModel['column'], $schemaSelected[$column])) {
                continue;
            }

            $this->sheet->setCellValue("{$this->colIdx}{$this->rowIdx}", $parentLabel.".".$infoModel['label']);
            $this->colIdx++;
            if(isset($infoModel['subschema'])) {
                $parents[] = $infoModel['column'];
                $this->recursiveColumns($infoModel, $parents);
            }
        }
    }
    
    private function recursiveRecords($infoModelParent, $parentModel, $parents = []) {
        $schema = $infoModelParent['subschema'];
        $column = $infoModelParent['column'];
        $parentLabel = $infoModelParent['label'];
        $class = $infoModelParent['class'];
        $modelInstance = app($class);
        $model = empty($parentModel) ? null : $modelInstance::find($parentModel->{$column});

        $schemaSelected = $this->schemaSelected;
        if(count($parents) > 1) {
            foreach($parents as $c) {
                if($column === $c) {
                    break;
                }
                $schemaSelected = $schemaSelected[$c];
            }
        }



        foreach($schema as $infoModel) {
            if(!\array_key_exists($infoModel['column'], $schemaSelected[$column])) {
                continue;
            }
            $val = empty($model) || empty($model->{$infoModel['column']}) ? "" : $model->{$infoModel['column']};

            if(method_exists($val,'toString')) {
                $val = $val->toString();
            } elseif(is_object($val) || is_array($val)) {
                $val = var_export($val, true);
            }
            $this->sheet->setCellValue("{$this->colIdx}{$this->rowIdx}", $val);
            $this->colIdx++;
            if(isset($infoModel['subschema'])) {
                $parents[] = $infoModel['column'];
                $this->recursiveRecords($infoModel, $model, $parents);
            }
        }
    }
    
    
    public function assets(Request $request) {
        try {
            $path = $request->get('path','');
            if(!$path) {
                return response()->json(null,Response::HTTP_NOT_FOUND);
            }
            $pathToFile = __DIR__."/../../publishable/assets/$path";

            if( !file_exists($pathToFile) ) {
                return response()->json($pathToFile,Response::HTTP_NOT_FOUND);
            }
            
            $mimeType = MimeType::from(basename($pathToFile));

            return response()->file($pathToFile,array(
                'Content-Type' => $mimeType,
            ));
        } catch (\Exception $e) {
            return response()->json(null,Response::HTTP_NOT_FOUND);
        }
    }

    public function settings(Request $request) {
        try {
            $filterSettings = $request->get('only', null);
            if(!empty($filterSettings)) {
                $filterSettings = array_map(function($setting){
                    return trim("admin.$setting");
                },explode(',',$filterSettings));
                $settings = Setting::whereIn('key', $filterSettings)->get();
            } else {
                $settings = Setting::all();
            }

            $retunListSettings = [];
            foreach($settings as $setting) {
                $key = \str_replace(\strtolower($setting->group).'.','',$setting->key);
                $retunListSettings[$key] = $setting->value;
            }

            return response()->json($retunListSettings);
        } catch (\Exception $e) {
            throw new ExportBreadException($e->getMessage());
        }
    }


    public function dataModel(Request $request) {
        try {
            $dataTypeId = $request->get('data_type_id', 0);
            $dataType = ExportBread::getDataType($dataTypeId);

            if(!$dataType) {
                throw new ExportBreadException(__('exportbread::general.model_not_found'));
            }

            $modelInstance = app($dataType->model_name);
            $json = new \stdClass;
            $json->schema = ExportBread::jsonSchemaColumns($modelInstance);

            return response()->json(array(
                'success' => true,
                'message' => "",
                'data' => $json,
            ));
        } catch (ExportBreadException $ebe) {
            return response()->json(array(
                'success' => false,
                'message' => $ebe->getMessage(),
            ));
        } catch (\Exception $e) {
            return response()->json(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }


    public function download(Request $request) {
        try {

            if( !auth()->user()->hasPermission('edit_settings') ) {
                throw new ExportBreadException( __('exportbread::general.error_permission') );
            }
            
            $disk = 'local';
            $path = config('filesystems.disks.'.$disk, [])['root'];
            $pathToFile = $path."/exports-bread/eb_generated_report.csv";
            
            if( !file_exists($pathToFile) ) {
                return response()->json($pathToFile,Response::HTTP_NOT_FOUND);
            }
            
            $mimeType = MimeType::from(basename($pathToFile));

            return response()->file($pathToFile,array(
                'Content-Type' => $mimeType,
            ));
        } catch (\Exception $e) {
            return response()->json(null,Response::HTTP_NOT_FOUND);
        }
    }

    
}
