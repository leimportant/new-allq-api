<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class KasbonController extends Controller
{

    public function list(Request $request $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Kasbon::leftJoin('statuses', 'statuses.id', '=', 'kasbon.status')
                        ->whereNull('deleted_at')
                        ->where('application', $application)
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

    public function view(Request $request $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Kasbon::with('activities')
                        ->where('application', $application)
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

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'user_id' => 'required',
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

            if ($request->amount <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Kasbon tidak boleh 0 (nol) atau kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }


            if (in_array($status, [2,3])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak boleh di edit kembali, data sudah di setujui',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $emp = User::find($request->user_id);
            $fullname = $emp->fullname ?? "";
            $company_id = $emp->company_id ?? "";

            if (!$fullname) {
                return response()->json([
                    'status' => false,
                    'message' => 'Nama Pengguna belum terdaftar atau nama tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            if ($exist) {
                $transaction_id = $request->id;
                $data = Kasbon::find($transaction_id);
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $request->status ?? 1;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = $fullname . " edit pengajuan kasbon sebesar " . $request->amount;

            } else {
                $transaction_id = $this->generateNumber(1);

                $data = new Kasbon;
                $data->id = $transaction_id;
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $request->status ?? 1;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();


                $descriptions = $fullname . " melakukan pengajuan kasbon sebesar " . $request->amount;
            }

            $act = Activities::create([
                'application' => $application,
                'transaction_id' => $transaction_id,
                'user_id' => $request->user_id,
                'descriptions' => $descriptions,
            ]);

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

    public function generateNumber($number, $application) {
        $year = $number . $application.  date('ym');
        $_f = '001';
        $sql =  Kasbon::select(DB::raw('MAX(id) AS id'))
                        ->where('id', 'LIKE', '%' . $year . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $_maxno = $sql->id;
        if (empty($_maxno)) {
            $no = $year . $_f;
        } else {
            $_sbstr = substr($_maxno, -3);
            $_sbstr++;
            $_new = sprintf("%03s", $_sbstr);
            $no = $year . $_new;
        }

      return $no;
    }
}
