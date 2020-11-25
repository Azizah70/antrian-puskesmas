<?php

namespace App\Http\Controllers;

use App\Schedule;
use Carbon\Carbon;
use App\HealthAgency;
use App\Polyclinic;
use App\PolyMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PolyclinicController extends Controller
{
    public function __construct() {
        $this->middleware('roleUser:Admin')->except(['show', 'ShowPolyclinicOfHA', 'userShowHealthAgency']);
        $this->middleware('roleUser:Admin,Super Admin,Pasien')->only(['show', 'ShowPolyclinicOfHA']);
        $this->middleware('roleUser:Pasien')->except(['userShowHealthAgency']);
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
            'poly_master_id' => 'required|numeric',
            'health_agency_id' => 'required|numeric',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $polyclinic = Polyclinic::create([
            'poly_master_id' => $request->poly_master_id,
            'health_agency_id' => $request->health_agency_id,
        ]);

        if($polyclinic)
            return response()->json([
                'success' => true,
                'message' => 'Add data successfully!',
                'polyclinic' => $polyclinic,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Add data failed!',
                'polyclinic' => $polyclinic,
            ], 500);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Polyclinic  $polyclinic
     * @return \Illuminate\Http\Response
     */
    public function show(Polyclinic $polyclinic)
    {
        return response()->json($polyclinic, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Polyclinic  $polyclinic
     * @return \Illuminate\Http\Response
     */
    public function edit(Polyclinic $polyclinic)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Polyclinic  $polyclinic
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Polyclinic $polyclinic)
    {
        $validator = Validator::make($request->all(), [
            'poly_master_id' => 'required|numeric',
            'health_agency_id' => 'required|numeric',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $isUpdate = Polyclinic::where('id', $polyclinic->id)->first()
            ->update([
            'poly_master_id' => $request->poly_master_id,
            'health_agency_id' => $request->health_agency_id,
            ]);

        $polyclinic = Polyclinic::where('id', $polyclinic->id)->first();

        if($isUpdate)
            return response()->json([
                'success' => true,
                'message' => 'Update data successfully!',
                'polyclinic' => $polyclinic,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Update data failed!',
                'polyclinic' => $polyclinic,
            ], 500);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Polyclinic  $polyclinic
     * @return \Illuminate\Http\Response
     */
    public function destroy(Polyclinic $polyclinic)
    {
        if ($polyclinic->delete()) {
            return response()->json([
                'success' => true,
                'message' => 'Delete data successfully!',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Delete data failed!',
            ], 500);
        }
    }

    public function userShowHealthAgency(PolyMaster $polymaster){
        $data = Polyclinic::where('poly_master_id', $polymaster->id)
            ->with('health_agency')->get();

        $results = [];
        foreach ($data as $row) {
            $results[] = $row->health_agency;
        }

        return response()->json($results, 200);
    }

    public function ShowPolyclinicOfHA(HealthAgency $healthAgency){
        $schedules = Polyclinic::with(['poly_master' => function($q){
            $q->select('id', 'name')->get();
        },'schedules'])
            ->where('health_agency_id', $healthAgency->id)->get();

        foreach($schedules as $row) {
            foreach($row["schedules"] as $schedule) {
                $day = Schedule::where('id', $schedule->id)->first()->day;
                $dayId = array_search($day, DAY);
                $dayId = ($dayId)%7;
                $today = Carbon::now()->dayOfWeek;
                $add = $dayId - $today;
                if($add < 0){ //jika selisih negatif brrti ganti date ke mingdep
                    $add += 7;
                }
                $schedule["day"] = $dayId;
                $schedule["date"] = (Carbon::now()->addDays($add)->toDateString());
            }
            //sorting based on index day
            $collection = collect($row["schedules"]);
            $sorted = $collection->sortBy('day');
            $row["sorted"] = $sorted->values()->all();

        }

        if($schedules)
            return response()->json([
                'success' => true,
                'message' => 'Get data success',
                'data' => $schedules,
            ], 200);
        else
            return response()->json([
                'success' => false,
                'message' => 'Get data failed',
                'data' => $schedules,
            ], 200);
    }


}
