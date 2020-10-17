<?php

namespace MDS\Fields\ImagePreCrop;

use Illuminate\Http\Request;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

use Auth;

use Intervention\Image\Facades\Image;

use TCG\Voyager\Facades\Voyager;

use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function uploadImage(Request $request) {

        $is_ajax = $request->input('ajax', false);
        $public_path = $request->input('public_path', 'general');

        $status = 'error';
        $message = __('cropimage::general.error_saving_image');
        $data = [];

        $user = Auth::user();

        if( !Auth::check() ) {
            $message = __('cropimage::general.denyaccess');
        } else {
            try {

                //$ext = $request->file('image')->extension();

                $path = Storage::putFile(
                    'public/'.$public_path, $request->file('image')
                );

                $status = 'success';
                $message = __('cropimage::general.data_saved_successfully');
                $data = array(
                    'full' => Voyager::image(str_replace('public/','',$path)),
                    'path' => $public_path,
                    'name' => str_replace('public/'.$public_path.'/','',$path),
                );
            } catch(\Exception $e) {
                $message = $e->getMessage();
            }
        }



        if( !$is_ajax ) {
            return redirect()
            ->route("cropimage.site.settings")
            ->with([
                'message'    => $message,
                'alert-type' => $status,
            ]);

        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'user' => $user,
        ]);
    }

    // Crop Image
    public function cropImage(Request $request) {
        $createMode = $request->get('createMode') === 'true';
        $x = $request->get('x');
        $y = $request->get('y');
        $height = $request->get('height');
        $width = $request->get('width');

        $filesystem = config('voyager.storage.disk');

        $realPath = Storage::disk($filesystem)->getDriver()->getAdapter()->getPathPrefix();
        $originImagePath = $realPath.$request->upload_path.'/'.$request->originImageName;

        $response = [];

        try {
            if ($createMode) {
                // create a new image with the cpopped data
                $fileNameParts = explode('.', $request->originImageName);
                array_splice($fileNameParts, count($fileNameParts) - 1, 0, 'cropped_'.time());
                $newImageName = implode('.', $fileNameParts);
                $destImagePath = $realPath.$request->upload_path.'/'.$newImageName;

                $response['newImage'] = array(
                    'url' => Voyager::image($request->upload_path.'/'.$newImageName),
                    'name' => $newImageName
                );
            } else {
                // override the original image
                $destImagePath = $originImagePath;
            }

            Image::make($originImagePath)->crop($width, $height, $x, $y)->save($destImagePath);

            $success = true;
            $message = __('cropimage::general.success_crop_image');
        } catch (\Exception $e) {
            $success = false;
            $message = $e->getMessage();
        }

        $response['success'] = $success;
        $response['message'] = $message;



        return response()->json($response);
    }
}
