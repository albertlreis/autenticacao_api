<?php
use Illuminate\Support\Facades\Route;
use App\Saas\ControlController;
if(config('saas.enabled')&&config('saas_control.enabled')) {
 Route::prefix('v1/control')->withoutMiddleware(['throttle:api',App\Saas\RequireModule::class])->middleware([App\Saas\ControlAuth::class,App\Saas\ControlIdempotency::class])->group(function(){
  Route::get('tenants',[ControlController::class,'index']);Route::post('tenants',[ControlController::class,'store']);
  Route::get('tenants/{id}',[ControlController::class,'show']);Route::patch('tenants/{id}',[ControlController::class,'update']);
  Route::post('tenants/{id}/provision',[ControlController::class,'provision']);Route::get('operations/{id}',[ControlController::class,'operation']);
 });
}
