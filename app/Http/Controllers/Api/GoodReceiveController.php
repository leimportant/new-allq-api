<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Orders;
use App\Models\GoodIssueMaterial;
use App\Models\GoodIssue;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use LaravelQRCode\Facades\QRCode as QRCode;
use Carbon\Carbon;
use Log;

class GoodReceiveController extends Controller
{

    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;

        $sql =  GoodIssue::with('orders')
                        ->with('details')
                        ->where('application', $application)
                        ->where('is_confirm', 1)
                        ->where('id', $filter);

        $data = $sql->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ], 200);
    }

    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->q;
        $month = substr($request->period,5,4);
        $year = substr($request->period,0,4);
        
        $sql = GoodIssue::with('orders')
                        ->with('details')
                        ->where('application', $application)
                        ->whereMonth('confirm_date', $month)
                        ->whereYear('confirm_date', $year)
                        ->where('is_confirm', 1);

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%')
                    ->orwhere('orders_id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('recipient_by', 'LIKE', '%' . $filter. '%')
                    ->orwhere('id', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('confirm_date', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }
    public function store(Request $request, $application) {
        $user_id = Auth::id();
        $now = Carbon::now()->timestamp;

        $validateUser = Validator::make($request->all(), 
        [
            'transaction_id' => 'required',
            'remark' => 'required',
            'recipient_by' => 'required'
        ]);

        if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Silakan isi data dengan lengkap',
                'errors' => $validateUser->errors()
            ], 200);
        }

        $data = GoodIssue::find($request->transaction_id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'errors' => $validateUser->errors()
            ], 200);
        }
       
        $data->remark = $request->remark ?? "";
        $data->is_confirm = 1;
        $data->recipient_by = $request->recipient_by;
        $data->confirmed_by = $user_id;
        $data->confirm_date = date("Y-m-d H:i:s");
        $data->update();

        return response()->json([
            'status' => true,
            'message' => 'Barang berhasil di terima',
            "transaction_id" => $request->transaction_id,
        ], 200);
    }
}
