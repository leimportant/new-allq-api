<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Material;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class MaterialController extends Controller
{

    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->q;
        $company = $request->company;
        
        $sql =  Material::where('application', $application);

        if ($filter) {
            $sql->where('name', 'LIKE', '%' . $filter. '%')
                    ->orwhere('company_id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('is_stock', 'LIKE', '%' . $filter. '%');
        }

        if ($company) {
            $sql->where('company_id', 'LIKE', '%' . $company. '%');
        }

        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Material::where('application', $application)
                        ->where('id', $filter);

        $data = $sql->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    public function store(Request $request, $application)
    {
        try {
            DB::beginTransaction();

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'name' => 'required',
                'uom' => 'required',
                'company_id' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $exist = Material::find($request->id);


            if ($exist) {
                $transaction_id = $request->id;
                $data = Material::find($transaction_id);
                $data->name = $request->name;
                $data->uom = $request->uom;
                $data->company_id = $request->company_id;
                $data->application = $application;
                $data->is_stock = $request->is_stock;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

            } else {
                $data = new Material;
                $data->name = $request->name;
                $data->uom = $request->uom;
                $data->company_id = $request->company_id;
                $data->application = $application;
                $data->is_stock = $request->is_stock;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();

            }
            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
            ], 200);

        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

}
