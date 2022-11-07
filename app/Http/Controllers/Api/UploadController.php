<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Upload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Storage;
use Carbon\Carbon;
use Intervention\Image\Facades\Image as Image;
use Log;

class UploadController extends Controller
{
    public function list(Request $request, $application)
    {
        $user_id = Auth::id();
        $transaction_id = $request->transaction_id;
        $filter = $request->q;
        $route = $request->route;
        
        $sql =  Upload::where('application', $application)
                        ->where('transaction_id', $transaction_id)
                        ->where('route', $route);

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%');
        }

        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        $data->getCollection()->transform(function ($value) use ($application) {
            $value->public_url = $this->urlImage($value->id, $application);
            return $value;
        });

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function delete(Request $request, $application)
    {
        try {

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'id' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Data belum lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $data = Upload::find($request->id);

            if(!$data){
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak di temukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $path = $data->path;
            $photo = $data->photo;
            $storage = "upload" . $request->route . $request->transaction_id;

            $url = Storage::disk('public')->delete($path);

            if(!$url) {
                 return response()->json([
                    'status' => false,
                    'message' => 'File tidak di temukan',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $data->delete();

            return response()->json([
                'status' => true,
                'message' => 'Delete Data Berhasil',
                "transaction_id" => $request->transaction_id,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }
        
    public function store(Request $request, $application)
    {
        try {

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $emp = User::find($user_id);
            $fullname = $emp->fullname ?? "";
            $company_id = $emp->company_id ?? "";

            if ($request->route == "profile") {
                $data = Upload::where('transaction_id', $request->transaction_id)
                                ->where('route', "profile")
                                ->first();

                if ($data) {
                    $path_delete = $data->path;    
                    $url_delete = Storage::disk('public')->delete($path_delete);

                    $delete = Upload::find($data->id);
                    $delete->delete();
                }
               
            }

            $validateUser = Validator::make($request->all(), 
            [
                'transaction_id' => 'required',
                'route' => 'required',
                'photo' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Upload data belum lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $image_64 = $request->photo; //your base64 encoded data

            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf

            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            $image = str_replace($replace, '', $image_64); 

            $image = str_replace(' ', '+', $image); 
            $storage = "upload" . "/".  $request->route . "/" . $request->transaction_id . "/";
            $imageName =  Str::random(10).'.'.$extension;

            $url = Storage::disk('public')->put($storage. $imageName, base64_decode($image));
            $path =  $storage . $imageName;
            
            if ($url) {
                $data = new Upload;
                $data->transaction_id = $request->transaction_id;
                $data->route = $request->route;
                $data->path = $path;
                $data->photo = $imageName;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->remark = $request->remark ?? "";
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $request->transaction_id,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

    public function loadImage(Request $request, $application)
    {
        try {
            $data = Upload::find($request->id);
            $storage =  $data->path ?? "";

            $destinationPath = storage_path('app/public') . '/' . $storage;
            $imgFile = Image::make($destinationPath);
            return $imgFile->response('jpg');

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

    public function urlImage($id, $application)
    {
        $data = Upload::where('id', $id)
                        ->first();
        
        if (!$data) {
            return '';
        }
        return env('BASE_URL') . "/api/image/". $application. "?id=" .$data->id;
    }
}
