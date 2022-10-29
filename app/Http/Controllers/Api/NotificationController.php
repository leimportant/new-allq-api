<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Events\MessageNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class NotificationController extends Controller
{
    public function list(Request $request, $application)
    {
        $user_id = $request->user_id ?? Auth::id();
        $filter = $request->id;
        $year = $request->year;
        
        $sql =  Notification::select([
                            DB::raw('notifications.*')
                        ])
                        ->where('application', $application)
                        ->whereYear('created_at', $year)
                        ->where(function($query) use($user_id) {
                            $query->where('assign_to', $user_id); 
                        });

        if ($filter) {
            $sql->where('route', 'LIKE', '%' . $filter. '%')
                    ->orwhere('assign_from', 'LIKE', '%' . $filter. '%')
                    ->orwhere('assign_date', 'LIKE', '%' . $filter. '%')
                    ->orwhere('transaction_id', 'LIKE', '%' . $filter. '%')
                    ->orwhere('message', 'LIKE', '%' . $filter. '%')
                    ->orwhere('assign_to', 'LIKE', '%' . $filter. '%');
        }


        $data = $sql->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function send($from, $to, $route, $transaction_id, $application, $descriptions)
    {

        $message = new Notification;
        $number = $this->generateNumber($application);
        $message->setAttribute('id', $number);
        $message->setAttribute('assign_from', $from);
        $message->setAttribute('assign_to', $to);
        $message->setAttribute('application', $application);
        $message->setAttribute('transaction_id', $transaction_id);
        $message->setAttribute('route', $route);
        $message->setAttribute('assign_date', date('Y-m-d'));
       
        $message->setAttribute('message', $descriptions);
        $message->save();
          
        // want to broadcast NewMessageNotification event
        event(new MessageNotification($message));
          
        return $message;
    }

    public function generateNumber($application) {
        $year = $application.  date('ymd');
        $_f = '0000001';
        $sql =  Notification::select(DB::raw('MAX(id) AS id'))
                        ->where('id', 'LIKE', '%' . $year . '%')
                        ->orderBy('id', 'desc')
                        ->first();

        $_maxno = $sql->id;
        if (empty($_maxno)) {
            $no = $year . $_f;
        } else {
            $_sbstr = substr($_maxno, -7);
            $_sbstr++;
            $_new = sprintf("%07s", $_sbstr);
            $no = $year . $_new;
        }

      return $no;
    }
}
