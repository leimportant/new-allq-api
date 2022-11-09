<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Orders;
use App\Models\OrdersItem;
use App\Models\Activities;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use LaravelQRCode\Facades\QRCode as QRCode;
use Carbon\Carbon;
use Log;

class OrdersController extends Controller
{

    public function view(Request $request, $application)
    {
        $user_id = Auth::id();
        $filter = $request->id;
        $sql =  Orders::with('items')
                        ->with('activities')
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

    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->q;
        
        $sql =  Orders::with('items')
                        ->select([
                            DB::raw('create_orders.*'),
                            DB::raw('statuses.name as status_name')
                        ])
                        ->leftJoin('statuses', 'statuses.id', '=', 'create_orders.status')
                        ->where('application', $application)
                        ->where('statuses.menu', 'document');

        if ($filter) {
            $sql->where('remark', 'LIKE', '%' . $filter. '%')
                    ->orwhere('date', 'LIKE', '%' . $filter. '%')
                    ->orwhere('total_roll', 'LIKE', '%' . $filter. '%')
                    ->orwhere('total', 'LIKE', '%' . $filter. '%')
                    ->orwhere('id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('name', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
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
                'date' => 'required',
                'total_roll' => ['required', 'numeric'],
                'total' => ['required', 'numeric'],
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $exist = Orders::find($request->id);
            $status = $exist->status ?? 1;

            if ($request->total_roll <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Jumlah roll tidak boleh 0 (nol) atau kosong',
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

            $emp = User::find($user_id);
            $company_id = $emp->company_id ?? "";

            if ($exist) {
                $status = $request->status ?? 1;
                $transaction_id = $request->id;
                $data = Orders::find($transaction_id);
                $data->date = $request->date ?? date("Y-m-d");
                $data->company_id = $request->company_id;
                $data->name = $request->name ?? "";
                $data->application = $application;
                $data->total_roll = $request->total_roll;
                $data->total = $request->total;
                $data->uom = $request->uom;
                $data->remark = $request->remark ?? "";
                $data->status = $status;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = "edit order model";

            } else {
                $transaction_id = $this->generateNumber(4, $application);
                $status = $request->status ?? 1;
                $data = new Orders;
                $data->id = $transaction_id;
                $data->date = $request->date;
                $data->company_id = $request->company_id ?? $company_id;
                $data->name = $request->name ?? "";
                $data->application = $application;
                $data->total_roll = $request->total_roll;
                $data->total = $request->total;
                $data->uom = $request->uom;
                $data->remark = $request->remark ?? "";
                $data->status = $status;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();

                $descriptions = "membuat order model";
            }

            if (count($request->items) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Item barang tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $delete = OrdersItem::where('orders_id', $transaction_id)->delete();
            foreach ($request->items as $row) {
                $material_id = $row['material_id'] ?? "";
                $material_name = $row['material_name'] ?? "";
                $qty = $row['qty'] ?? 0;
                $uom = $row['uom'] ?? "";

                if (!$material_id) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Data barang tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                if (!$qty || !$uom) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Qty dan Unit barang tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                $check = OrdersItem::where('orders_id', $transaction_id)
                                  ->where('material_id', $material_id)
                                  ->first();

                if ($check) {
                    $data = OrdersItem::find($check->id);
                    $data->material_id = $material_id;
                    $data->orders_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->update();
                } else {
                    $data = new OrdersItem;
                    $data->material_id = $material_id;
                    $data->orders_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->save();
                }

            }

            $Approval = "";

            if ($status == 2) {
                $message  = $descriptions; 
                $Approval = (new ApprovalController)->store($request, $transaction_id, $company_id, 'order-model', $application, $message);
            }

            $act = Activities::create([
                'application' => $application,
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'descriptions' => $descriptions,
            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $transaction_id,
                "notification" => $Approval
            ], 200);
          
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }
    
    public function qrcode(Request $request, $application)
    {
        $user_id = Auth::id();
        $now = Carbon::now()->timestamp;

        $validateUser = Validator::make($request->all(), 
        [
            'id' => 'required',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Qrcode data tidak ditemukan',
                'errors' => $validateUser->errors()
            ], 200);
        }

        $data = Orders::find($request->id);
        return QRCode::text($data->id)->svg();   
        

    }

    public function finish(Request $request, $application)
    {
        $user_id = Auth::id();
        $now = Carbon::now()->timestamp;

        $validateUser = Validator::make($request->all(), 
        [
            'transaction_id' => 'required',
            'remark' => 'required',
        ]);

        if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => 'Silakan isi data dengan lengkap',
                'errors' => $validateUser->errors()
            ], 200);
        }

        $data = Orders::find($request->transaction_id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'errors' => $validateUser->errors()
            ], 200);
        }

        $data->remark = $request->remark ?? "";
        $data->status = 5;
        $data->updated_by = $user_id;
        $data->updated_at = $now;
        $data->update();

        return response()->json([
            'status' => true,
            'message' => 'Finish data berhasil, data tidak muncul di list dashboard',
            "transaction_id" => $request->transaction_id,
        ], 200);
    }

    public function generateNumber($number, $application) {
        $year = $number . $application.  date('ym');
        $_f = '0001';
        $sql =  Orders::select(DB::raw('MAX(id) AS id'))
                        ->where('id', 'LIKE', '%' . $year . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $_maxno = $sql->id;
        if (empty($_maxno)) {
            $no = $year . $_f;
        } else {
            $_sbstr = substr($_maxno, -4);
            $_sbstr++;
            $_new = sprintf("%04s", $_sbstr);
            $no = $year . $_new;
        }

      return $no;
    }
}
