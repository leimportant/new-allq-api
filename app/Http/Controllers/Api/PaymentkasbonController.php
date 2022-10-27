<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Kasbon;
use App\Models\Employee;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\KasbonController;
use App\Http\Controllers\Api\ApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class PaymentkasbonController extends Controller
{

    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Kasbon::with('activities')
                        ->where('application', $application)
                        ->where('kasbon_type', 2)
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
                    'message' => 'Pembayaran Kasbon tidak boleh 0 (nol) atau kosong',
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

            $dash_kasbon = (new KasbonController)->dashboard($request, $application, 1);
            $dash_bayar = (new KasbonController)->dashboard($request, $application, 2);

            $kasbon  = $dash_kasbon;
            $bayar   = floatval($dash_bayar + $request->amount);
            $total_kasbon  = floatval($dash_kasbon - $dash_bayar);

            if ($total_kasbon < 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Pembayaran melebihi kasbon, Silahkan cek kembali',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            if ($exist) {
                $transaction_id = $request->id;
                $status = $exist->status ?? 1;

                $data = Kasbon::find($transaction_id);
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark;
                $data->status = $status;
                $data->kasbon_type = 2;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = " edit pengajuan pembayaran kasbon sebesar " . $request->amount;

            } else {
                $transaction_id = $this->generateNumber(2, $application);
                $status = $exist->status ?? 1;
                $data = new Kasbon;
                $data->id = $transaction_id;
                $data->user_id = $request->user_id;
                $data->fullname = $fullname;
                $data->company_id = $company_id;
                $data->application = $application;
                $data->amount = $request->amount;
                $data->remark = $request->remark ?? "Pembayaran";
                $data->status = $status;
                $data->kasbon_type = 2;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();


                $descriptions = " melakukan pengajuan pembayaran kasbon sebesar " . $request->amount;
            }

            if ($status == 1) {
                $Approval = (new ApprovalController)->store($request, $transaction_id, $company_id, 'payment-kasbon', $application);
            }


            $act = Activities::create([
                'application' => $application,
                'transaction_id' => $transaction_id,
                'user_id' => $request->user_id,
                'descriptions' => $descriptions,
            ]);

            $this->UpdateKasbon($transaction_id, $request->user_id);
           
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 200);
        }
    }

    public function UpdateKasbon($transaction_id, $user_id) {
        $kasbon = Kasbon::select([
                                DB::raw('SUM(amount) as amount')
                            ])
                          ->where('user_id', $user_id)
                          ->whereIn('status', [1,2,3])
                          ->where('kasbon_type', 1)
                          ->first();
        $amount_kasbon = $kasbon->amount ?? 0;

        $bayar = Kasbon::select([
                    DB::raw('SUM(amount) as amount')
                ])
                ->where('user_id', $user_id)
                ->whereIn('status', [1,2,3])
                ->where('kasbon_type', 2)
                ->first();

        $amount_bayar = $bayar->amount ?? 0;
        $total_kasbon = floatval($amount_kasbon - $amount_bayar);
        $update_total = Kasbon::where('id', $transaction_id)
                        ->update([
                                'total_kasbon' => $total_kasbon
                        ]);

        return true;
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
