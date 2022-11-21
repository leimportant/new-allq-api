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
        $user_id = Auth::id();
        $filter = $request->id;
        $read = $request->read;
        $year = $request->year ?? date("Y");
        
        $sql =  Notification::select([
                            DB::raw('notifications.*')
                        ])
                        ->where('application', $application)
                        ->whereYear('created_at', $year)
                        ->where(function($query) use($user_id) {
                            $query->where('assign_to', $user_id); 
                        });
        
        if ($read == 1) {
            $sql->whereNotNull('read_at');
        } else {
            $sql->whereNull('read_at');
        }

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
            'total_unread' => $this->totalNotification($application, 0),
            'total_read' => $this->totalNotification($application, 1),
            'data' => $data,
        ], 200);
    }

    public function totalNotification($application, $read)
    {
        $user_id = Auth::id();
        $sql =  Notification::where('application', $application)
                            ->where(function($query) use($user_id) {
                                $query->where('assign_to', $user_id); 
                            });

        if ($read == 1) {
            $sql->whereNotNull('read_at');
        } else {
            $sql->whereNull('read_at');
        }

        $data = $sql->get();

        $count = count($data);

        return $count;

    }
    public function markAsRead(Request $request, $application)
    {
        $user_id = Auth::id();
        $id = explode(",",$request->id);

        Notification::whereIn('id', $id)
                        ->where('assign_to', $user_id)
                            ->update([
                                'read_at' => date("Y-m-d H:i:s")
                        ]);

        return response()->json([
            'status' => true,
            'message' => 'Data Berhasil',
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
        
        $pusher = new \Pusher\Pusher(
			'ca9f78e3c7f352d4843f',
			'cc591e33ac8a59e8cb00',
			'1498493',
			[
				'cluster' => 'ap1',
				'useTLS' => true
			]
		);
		  
		$pusher->trigger('my-channel', 'my-event', $message);
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
