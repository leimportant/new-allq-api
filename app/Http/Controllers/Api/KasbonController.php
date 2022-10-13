<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class KasbonController extends Controller
{

    public function list(Request $request)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Kasbon::whereNull('deleted_at')
                        ->where('created_by', $user_id);

        if ($filter) {
            $sql->where('remark', $filter)
                    ->orwhere('fullname', $filter)
                    ->orwhere('amount', $filter);
        }


        $data = $sql->paginate();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    public function store(Request $request)
    {
        try {

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'employee_id' => 'required',
                'amount' => ['required', 'numeric'],
                'remark' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $exist = Kasbon::find($request->id);
            $status = $exist->status ?? 1;
            if (in_array($status, [2,3])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak boleh di edit kembali, data sudah di setujui',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $emp = Employee::find($request->employee_id);
            $fullname = $emp->fullname ?? "";

            if ($exist) {
                $data = Kasbon::find($request->id);
                $data->employee_id = $request->employee_id;
                $data->fullname = $fullname;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $request->status ?? 1;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->approval_level = $request->approval_level;
                $data->update();
            } else {
                $data = new Kasbon;
                $data->id = rand();
                $data->employee_id = $request->employee_id;
                $data->fullname = $fullname;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $request->status ?? 1;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->approval_level = 1;
                $data->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }
}
