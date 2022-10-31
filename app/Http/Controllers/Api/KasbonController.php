<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\PaymentkasbonController;
use App\Http\Controllers\Api\ApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class KasbonController extends Controller
{

    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->id;
        $month = substr($request->period,5,4);
        $year = substr($request->period,0,4);
        
        $sql =  Kasbon::select([
                            DB::raw('kasbon.*'),
                            DB::raw('statuses.name as status_name')
                        ])
                        ->leftJoin('statuses', 'statuses.id', '=', 'kasbon.status')
                        ->whereNull('deleted_at')
                        ->where('application', $application)
                        ->where('statuses.menu', 'kasbon')
                        ->whereMonth('created_at', $month)
                        ->whereYear('created_at', $year)
                        ->where(function($query) use($user_id) {
                            $query->where('user_id', $user_id); 
                       });

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%')
                    ->orwhere('fullname', 'LIKE', '%' . $filter. '%')
                    ->orwhere('amount', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        $kasbon  = $this->dashboard($request, $application, 1);
        $bayar  = $this->dashboard($request, $application, 2);
        $total_kasbon  = floatval($kasbon - $bayar);
        return response()->json([
            'status' => true,
            'kasbon' => $kasbon,
            'bayar' => $bayar,
            'total_kasbon' => $total_kasbon,
            'data' => $data,
        ], 200);
    }

    public function dashboard($request, $application, $kasbon_type)
    {
        $user_id = $request->user_id ?? Auth::id();

        $data = Kasbon::select([
                    DB::raw('SUM(amount) as amount')
                ])
                ->where('user_id', $user_id)
                ->whereIn('status', [1,2,3])
                ->where('kasbon_type', $kasbon_type)
                ->groupBy('kasbon_type')
                ->first();
            
        return floatval($data->amount ?? 0);
    }
    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Kasbon::with('activities')
                        ->where('application', $application)
                        ->where('id', $filter);

        $data = $sql->get();
        $result= [];
        foreach($data as $row) {
            $activities = [];
            foreach($row->activities as $act) {
                $user_id = $act->user_id;
                $usr = User::find($user_id);
                $fullname = $usr->fullname ?? "";
                $descriptions = $fullname . ' ' . $act->descriptions;
                $act->descriptions = $descriptions;
                $activities[] = $act;
            } 

            $result[] = $row;
        }

        return response()->json([
            'status' => true,
            'data' => $result
        ], 200);
    }

    public function store(Request $request, $application)
    {
        // DB::beginTransaction();
        // try {

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
                $status = $request->status ?? 1;
                $transaction_id = $request->id;
                $data = Kasbon::find($transaction_id);
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $status;
                $data->kasbon_type = 1;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = " edit pengajuan kasbon sebesar " . $request->amount;

            } else {
                $transaction_id = $this->generateNumber(1, $application);
                $status = $request->status ?? 1;
                $data = new Kasbon;
                $data->id = $transaction_id;
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $status;
                $data->kasbon_type = 1;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();


                $descriptions = " melakukan pengajuan kasbon sebesar " . $request->amount;
            }
            $Approval = [];

            if ($status == 1) {
                $message  = $fullname . $descriptions; 
                $Approval = (new ApprovalController)->store($request, $transaction_id, $company_id, 'kasbon', $application, $message);
            }

            $act = Activities::create([
                'application' => $application,
                'transaction_id' => $transaction_id,
                'user_id' => $request->user_id,
                'descriptions' => $descriptions,
            ]);

            $updateKasbon = (new PaymentkasbonController)->UpdateKasbon($transaction_id, $request->user_id);

            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id,
                "notification" => $Approval
            ], 200);

        //     DB::commit();
        // } catch (\Exception $ex) {
        //     DB::rollback();
        //     return response()->json(['error' => $ex->getMessage()], 500);
        // }
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
