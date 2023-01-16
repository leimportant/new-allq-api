<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Orders;
use App\Models\GoodIssueMaterial;
use App\Models\GoodIssue;
use App\Models\Activities;
use App\Models\OrdersAssignJob;
use App\Models\OrdersDetail;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\NotificationController;
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
        $sql =  Orders::with('details')
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
        
        $sql =  Orders::select([
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
                'start_date' => 'required',
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

            if ($request->id && !$exist) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan',
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
                $data->start_date = $request->start_date ?? date("Y-m-d");
                $data->company_id = $request->company_id;
                $data->name = $request->name ?? "";
                $data->application = $application;
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
                $data->start_date = $request->start_date;
                $data->company_id = $request->company_id ?? $company_id;
                $data->name = $request->name ?? "";
                $data->application = $application;
                $data->remark = $request->remark ?? "";
                $data->status = $status;
                $data->created_by = $user_id;
                $data->updated_by = $user_id;
                $data->created_at = $now;
                $data->updated_at = $now;
                $data->save();

                $descriptions = "berhasil membuat order model";
            }

            if (count($request->details) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data detail barang tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $delete = OrdersDetail::where('orders_id', $transaction_id)->delete();
            foreach ($request->details as $row) {
                $id = $row['id'] ?? "";
                $color = $row['color'] ?? "";
                $size = $row['size'] ?? "";
                $qty = $row['qty'] ?? 0;
                $uom = $row['uom'] ?? "";

                if (!$color) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Warna tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                if (!$size || !$qty) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Size dan Qty barang tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                $check = OrdersDetail::find($id);

                if ($check) {
                    $data = OrdersDetail::find($check->id);
                    $data->size = $size;
                    $data->orders_id = $transaction_id;
                    $data->color = $color;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->update();
                } else {
                    $data = new OrdersDetail;
                    $data->size = $size;
                    $data->orders_id = $transaction_id;
                    $data->color = $color;
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

        $form = explode("!", $request->id);

        $id = $form[0];
        $transaction = $form[1] ?? "";


        // $data = GoodIssue::find($request->id);
        return QRCode::text($id)->svg();   

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
        $data->end_date = date("Y-m-d H:i:s");
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

    // Create Pengeluaran Barang
    public function storeMaterial(Request $request, $application)
    {
        try {
            DB::beginTransaction(); 

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'orders_id' => 'required',
                'recipient_by' => 'required',
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

            $exist = GoodIssue::find($request->id);
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

            if ($exist) {
                $status = $request->status ?? 1;
                $transaction_id = $request->id;
                $data = GoodIssue::find($transaction_id);
                $data->application = $application;
                $data->orders_id = $request->orders_id;
                $data->total_roll = $request->total_roll;
                $data->recipient_by = $request->recipient_by;
                $data->total = $request->total;
                $data->uom = $request->uom;
                $data->remark = $request->remark ?? "";
                $data->status = $status;
                $data->updated_by = $user_id;
                $data->updated_at = $now;
                $data->update();

                $descriptions = "edit data barang";

            } else {
                $transaction_id = $this->generateNumberMaterial(5, $application);
                $status = $request->status ?? 1;
                $data = new GoodIssue;
                $data->id = $transaction_id;
                $data->orders_id = $request->orders_id;
                $data->application = $application;
                $data->recipient_by = $request->recipient_by;
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

                $descriptions = "data barang berhasil";
            }

            if (count($request->items) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Item barang tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $delete = GoodIssueMaterial::where('item_material_id', $transaction_id)->delete();
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

                $check = GoodIssueMaterial::where('item_material_id', $transaction_id)
                                  ->where('material_id', $material_id)
                                  ->first();

                if ($check) {
                    $data = GoodIssueMaterial::find($check->id);
                    $data->material_id = $material_id;
                    $data->good_issue_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->update();
                } else {
                    $data = new GoodIssueMaterial;
                    $data->material_id = $material_id;
                    $data->good_issue_id = $transaction_id;
                    $data->material_name = $material_name;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->save();
                }

            }

            $notif = (new NotificationController)->send($user_id, $request->recipient_by, 'good-issue', $transaction_id, $application, $descriptions);

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
                "notification" => $notif
            ], 200);
          
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    // assign job
    public function storeAssignjob(Request $request, $application)
    {
        try {
            DB::beginTransaction(); 

            $user_id = Auth::id();
            $now = Carbon::now()->timestamp;

            $validateUser = Validator::make($request->all(), 
            [
                'orders_id' => 'required',
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'Silakan isi data dengan lengkap',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            if (count($request->assignjob) === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data Item barang tidak boleh kosong',
                    'errors' => $validateUser->errors()
                ], 200);
            }

            $delete = OrdersAssignJob::where('orders_id', $request->orders_id)->delete();
            foreach ($request->assignjob as $row) {
                $id = $row['id'] ?? "";
                $route = $row['route'] ?? "";
                $name = $row['name'] ?? "";
                $price = $row['price'] ?? "";
                $qty = $row['qty'] ?? 1;
                $uom = $row['uom'] ?? "pcs";
                $remark = $row['remark'] ?? "";

                if (!$route) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Job tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                if (!$price) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Harga kerja tidak boleh kosong',
                        'errors' => $validateUser->errors()
                    ], 200);
                }

                $check = OrdersAssignJob::where('id', $id)
                                  ->first();

                if ($check) {
                    $data = OrdersAssignJob::find($check->id);
                    $data->orders_id = $request->orders_id;
                    $data->name = $name;
                    $data->route = $route;
                    $data->price = $price;
                    $data->remark = $remark;
                    $data->qty = $qty;
                    $data->uom = $uom;
                    $data->updated_by = $user_id;
                    $data->updated_at = $now;
                    $data->update();
                } else {
                    $data = new OrdersAssignJob;
                    $data->orders_id = $request->orders_id;
                    $data->name = $name;
                    $data->route = $route;
                    $data->price = $price;
                    $data->qty = $qty;
                    $data->remark = $remark;
                    $data->uom = $uom;
                    $data->created_by = $user_id;
                    $data->updated_by = $user_id;
                    $data->created_at = $now;
                    $data->updated_at = $now;
                    $data->save();
                }

            }

             $act = Activities::create([
                'application' => $application,
                'transaction_id' => $request->orders_id,
                'user_id' => $user_id,
                'descriptions' => "setting harga pekerjaan berhasil",
            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Data Berhasil di simpan',
                "transaction_id" => $request->orders_id,
                "notification" => ""
            ], 200);
          
        } catch (\Exception $ex) {
            DB::rollback();
            return response()->json(['error' => $ex->getMessage()], 500);
        }
    }

    public function scanQrCode(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $id = $request->id;
        
        $sql =  GoodIssue::with('orders')
                        ->with('details')
                        ->where('application', $application)
                        ->where('id', $id);

        $data = $sql->get();

        if(count($data) == 0) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
            ], 200);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
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

    public function generateNumberMaterial($number, $application) {
        $year = $number . $application.  date('ym');
        $_f = '0001';
        $sql =  GoodIssue::select(DB::raw('MAX(id) AS id'))
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
