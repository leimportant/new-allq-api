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
use Log;

class UploadController extends Controller
{

    public function delete(Request $request, $application)
    {
        try {

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'id' => 'required',
                'transaction_id' => 'required',
                'route' => 'required',
                
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

            $storage = "upload" . "/".  $data->route . "/" . $data->transaction_id . "/";
            $imageName = $storage. $data->photo;

            if(file_exists(storage_path($imageName))){
                 unlink(storage_path($imageName));
            }else{
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
            $path =  "app/public" .  "/" . $storage . $imageName;
            
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
}
