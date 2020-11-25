<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\WaitingList;
use App\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WaitingListController extends Controller
{
    public function __construct() {
        $this->middleware('roleUser:Admin')->only(['getAdminWaitingList', 'changeStatus', 'checkPatientQRCode']);
        $this->middleware('roleUser:Pasien')->only(['store']);
        $this->middleware('roleUser:Admin,Pasien')->only(['update', 'destroy']);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'schedule' => 'required|numeric',
            'registered_date' => 'required|date',
            'residence_number' => 'required|numeric|digits:16',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }
        //validate date and schedule
        $schedule = Schedule::where('id', $request->schedule)->first();
        if($schedule)
            $message = $this->validateScheduleDate($schedule, $request->registered_date);
        else
            $message = "Jadwal tidak terdaftar!";

        if($message != "")
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 200);

        $ordered = WaitingList::select('id')
            ->where('residence_number', $request->residence_number)
            ->where('schedule_id', $request->schedule)
            ->where('registered_date', $request->registered_date)
            ->first();

        if($ordered != null)
            return response()->json($validator->errors([
                'success' => false,
                'message' => "Anda sudah mendaftar di jadwal ini dengan NIK yang sama",
            ]), 200);

        $latestOrder = WaitingList::select('order_number')
            ->where('registered_date', $request->registered_date)
            ->where('schedule_id', $request->schedule)
            ->latest()->first();

        if($latestOrder == null) {
            $latestOrder = new WaitingList();
        }
        $latestOrder->order_number++;

        $waitingListId = WaitingList::create([
            'user_id' => Auth::id(),
            'schedule_id' => $request->schedule,
            'registered_date' => $request->registered_date,
            'order_number' => $latestOrder->order_number,
            'residence_number' => $request->residence_number,
            'status' => 'Belum Diperiksa',
        ])->id;

        $waitingListUpdated = WaitingList::where('id', $waitingListId)
            ->update([
                'barcode' => $waitingListId . '_' . $request->residence_number,
            ]);

        $waitingList = DB::table('waiting_list_view')
                        ->where('id', $waitingListId)
                        ->first();

        if($waitingListUpdated)
            return response()->json([
                'success' => true,
                'message' => 'Sukses mendaftar antrian!',
                'data' => $waitingList,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Add data failed!',
            ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\WaitingList  $waitingList
     * @return \Illuminate\Http\Response
     */
    public function show(WaitingList $waitingList)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\WaitingList  $waitingList
     * @return \Illuminate\Http\Response
     */
    public function edit(WaitingList $waitingList)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\WaitingList  $waitingList
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WaitingList $waitingList)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $updated = WaitingList::where('id', $waitingList->id)
            ->update([
                'status' => $request->status,
            ]);

        $waiting_list = WaitingList::where('id', $waitingList->id)->first();

        if($updated)
            return response()->json([
                'success' => true,
                'message' => 'Update data successfully!',
                'data' => $waiting_list,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Update data failed!',
                'data' => $waiting_list,
            ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\WaitingList  $waitingList
     * @return \Illuminate\Http\Response
     */
    public function destroy(WaitingList $waitingList)
    {
        if ($waitingList->delete())
            return response()->json([
                'success' => true,
                'message' => 'Delete data successfully!'
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Delete data failed!'
            ], 200);
    }

    /**
     * @notes : mengambil data semua antrian yang dimiliki pasien (yang lalu, hari ini, atau hari berikutnya)
     */
    public function getWaitingList() {
        $userId = Auth::id();
        date_default_timezone_set ('Asia/Jakarta');

        $currentWaitingList = DB::table('waiting_list_view')
                                ->where('user_id', $userId)
                                ->where('registered_date', date('Y-m-d'))
                                ->where('status', 'Belum Diperiksa')
                                ->get();

        $futureWaitingList = DB::table('waiting_list_view')
                                ->where('user_id', $userId)
                                ->where('registered_date', '>', date('Y-m-d'))
                                ->where('status', 'Belum Diperiksa')
                                ->get();

        $historyWaitingList = DB::table('waiting_list_view')
                                ->where('user_id', $userId)
                                ->where('registered_date', '<=', date('Y-m-d'))
                                ->where(function($q) {
                                    $q->where('status', 'Dibatalkan')
                                      ->orWhere('status', 'Sudah Diperiksa');
                                })
                                ->get();

        return response()->json([
            'success' => true,
            'waitingList' => [
                'currentWaitingList' => $currentWaitingList,
                'futureWaitingList' => $futureWaitingList,
                'historyWaitingList' => $historyWaitingList,
            ],
        ], 200);
    }

    /**
     * @notes : mengambil antrian terdekat dari antrian yang dimiliki pasien (ditampilkan di home)
     */
    public function showNearestWaitingList() {
        $userId = Auth::id();

        $waitingList = DB::table('waiting_list_view')
            ->where('user_id', $userId)
            ->where('distance_number', '>=', '0')
            ->where('registered_date', '>=', date('Y-m-d'))
            ->where(function($q) {
                $q->where('status', 'Belum Diperiksa')
                    ->orWhere('status', 'Sedang Diperiksa');
            })->first();

        if($waitingList)
            return response()->json([
                'success' => true,
                'message' => "Successfully get nearest waiting list",
                'data' => $waitingList,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => "You don\'t have any nearest waiting list",
            ], 200);
    }

    /**
     * @notes : mengambil data jumlah antrian saat ini untuk poli terkait pada tanggal terkait
     */
    public function getCurrentWaitingListRegist(Schedule $schedule, $date){
        $message = $this->validateScheduleDate($schedule, $date);
        if($message != "")
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 200);

        $currentWaitingList = DB::table('waiting_list_view')
            ->select('current_number', 'latest_number', 'health_agency', 'polyclinic', 'day')
            ->where('schedule_id', $schedule->id)
            ->where('registered_date', $date)
            ->first();

        if(!$currentWaitingList) {
            $schedule = Schedule::find($schedule->id);
            $poly = $schedule->polyclinic;
            $currentWaitingList = new WaitingList();
            $currentWaitingList->current_number = 0;
            $currentWaitingList->latest_number = 0;
            $currentWaitingList->health_agency = $poly->health_agency->name;
            $currentWaitingList->polyclinic = $poly->poly_master->name;
            $currentWaitingList->day = $schedule->day;
            $currentWaitingList->registered_date = $date;
        }

        return response()->json([
            'success' => true,
            'message' => "Get the current and latest number in specific schedule and date",
            'data' => $currentWaitingList,
        ], 200);
    }

    /**
     * @notes : melakukan validasi antara jadwal poli dengan tanggal yang diajukan calon pasien
     */
    private function validateScheduleDate(Schedule $schedule, $date) {
        $date = Carbon::parse($date);
        $today = Carbon::today();

        $dayOfSchedule = array_search($schedule->day, DAY);
        $dayOfDate = $date->dayOfWeek;
        $timeClose = Carbon::parse($schedule->time_close);

        //jika antara jadwal dan tanggal memiliki hari yang berbeda
        if($dayOfSchedule != $dayOfDate)
            return "Maaf, tanggal pilihan anda tidak sesuai dengan jadwal di puskesmas";

        //jika mencoba mendaftar untuk hari yang lalu
        //atau
        //jika tanggal pendaftaran lebih dari seminggu dari hari ini
        if($date < $today || $today->floatDiffInDays($date, false) > 7)
            return "Maaf, anda hanya bisa mendaftar untuk satu minggu ke depan";

        //jika hari ini dan waktu sekarang 30 menit sebelum puskesmas tutup
        if($today == $date)
            if($today->nowWithSameTz()->format('H:i') > $timeClose->addMinutes(-30)->format('H:i'))
                return "Maaf, puskesmas sudah tutup. Anda hanya bisa mendaftar untuk hari lainnya";

        return "";
    }

    public function getAdminWaitingList(){
        $waiting_list = DB::table('waiting_list_view')
            ->select(
                'id','residence_number', 'user_id as user_name',
                'order_number', 'polyclinic', 'status'
            )
            ->where('health_agency_id', Auth::user()->health_agency_id)
            ->where('registered_date', date('Y-m-d'))
            ->paginate(5);

        foreach ($waiting_list as $list){
            $list->user_name = User::where('id', $list->user_name)->first()->name;
        }

        if($waiting_list){
            return response()->json([
                'success' => true,
                'message' => "Successfully get waiting list of health agency",
                'data' => $waiting_list,
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => "Waiting list is empty",
            ], 200);
        }
    }

    public function changeStatus(WaitingList $waiting_list, $status){
        /**
         * @notes : 1 = Belum Diperiksa, 2 = Sedang Diperiksa, 3 = Sudah Diperiksa, 4 = Dibatalkan
         */

        $waiting_list->status = PATIENT_STATUS[$status-1];
        $waiting_list->updated_at = Carbon::now();

        $message = [
            "Antrian diterima", "Antrian berhasil di proses",
            "Antrian berhasil di selesaikan", "Antrian berhasil di batalkan"
        ];

        if($waiting_list->save())
            return response()->json([
                'success' => true,
                'message' => $message[$status-1],
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => "Antrian gagal di proses",
            ], 200);
    }

    public function checkPatientQRCode($qr_code){
        $waiting_list = DB::table('waiting_list_view')
            ->where('barcode', $qr_code)
            ->where('status', '=', 'Belum Diperiksa')
            ->first();

        if($waiting_list)
            return response()->json([
                'success' => true,
                'message' => "Antrian berhasil di terima",
                'data' => $waiting_list,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => "Antrian telah di terima sebelumnya",
            ], 200);
    }
}
