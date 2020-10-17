<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::group(['middleware' => ['auth:api']], function () {
    Route::group(['prefix'=>'auth','as'=>'auth.'], function (){
        Route::post('logout', 'AuthController@logout')->name('logout');
        Route::post('refresh', 'AuthController@refresh')->name('refresh');
    });

    Route::post('change-password/{user}', 'UserController@changePassword')
    ->name('user.change-password');
    Route::post('change-image/{user}', 'UserController@changeImage')
    ->name('user.change-image');
    Route::resource('user', 'UserController');
});

Route::group(['prefix'=>'auth','as'=>'auth.'], function (){
    Route::post('register', 'AuthController@register')->name('register');
    Route::post('login', 'AuthController@login')->name('login');
});

Route::group(['middleware' => ['auth:api'], 'prefix' => 'admin'], function (){
   Route::resource('health-agency', 'HealthAgencyController');
   Route::resource('poly-master', 'PolyMasterController');
   Route::resource('schedule', 'ScheduleController');
   Route::resource('waiting-list', 'WaitingListController');
});
