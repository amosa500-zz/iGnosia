<?php

namespace App\Console\Commands;

use App\businesslog;
use App\DailyLog;
use App\Plan;
use Carbon\Carbon;
use function date_default_timezone_set;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use function isNonEmptyString;
use function strtotime;

class DailyProfit extends Command
{

    protected $signature = 'daily:profit';


    protected $description = 'Daily User Profit';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
          $fetch_planA = DB::table('plans')->where('activation', 1)->get();
        foreach ($fetch_planA as $itemA) {

            if ($itemA->plan_type == 'A'){
                date_default_timezone_set('UTC');

            $tomorrow = Carbon::parse($itemA->activation_date);
            $now = Carbon::now()->subHour();

              /*  $dtH = $now->diffInHours($tomorrow);
                $dtM = $now->diffInMinutes($tomorrow,false);
               \Log::info('Passing A - with - '.$itemA->user_id.'->diffHours = '.$dtH);
                \Log::info(' -----------------------------------------------------------');
                \Log::info('Passing A - with - '.$itemA->user_id.'->diffMinutes = '.$dtM);*/


                if ($now->diffInMinutes($tomorrow, false) <= 1) {
                    $countprofit = DailyLog::where('user_id', $itemA->user_id)->where('plan_type', "A")->count();
                    if ($countprofit <= $itemA->plan_days) {
                        $dailylog = new DailyLog();
                        $dailylog->user_id = $itemA->user_id;
                        $dailylog->title = $itemA->plan_name;
                        $dailylog->amount = 0.03 * $itemA->plan_value;
                        $dailylog->type = "Profit";
                        $dailylog->plan_type = "A";
                        $dailylog->date = Carbon::now("Africa/Lagos");
                        $dailylog->save();
                        //New Plans profit date instance created@#af
                        $addDay = $now->addDay()->addHour()->toDateTimeString();
                        Plan::where('user_id', $itemA->user_id)
                            ->update(array(
                                'activation_date' => $addDay
                            ));
                        $checkforuser = businesslog::where('user_id', $itemA->user_id)->first();
                        if (empty($checkforuser)) {
                            $total = new businesslog();
                            $total->user_id = $itemA->user_id;
                            $total->daily_profit = DB::table('daily_logs')->where('user_id', $itemA->user_id)->where('plan_type', 'A')->sum('amount');
                            $total->save();
                        } else {
                            $profitofA = DB::table('businesslogs')->where('user_id', $itemA->user_id)->value('daily_profit');
                            $addProfit = DB::table('daily_logs')->where('user_id', $itemA->user_id)->where('plan_type','A')->latest()->value('amount');
                            businesslog::where('user_id', $itemA->user_id)
                                ->update(array(

                                    'daily_profit' => $profitofA + $addProfit
                                ));
                        }


                    } else {
                        Plan::where('user_id', $itemA->user_id)
                            ->where('plan_type', 'A')
                            ->update(array(
                                'activation_date' => Carbon::create(0000, 0, 0, 0, 0, 0),
                                'activation' => 0
                            ));
                    }


                }


        }
            //Plan B

            if ($itemA->plan_type == 'B')
            {

                $tomorrow = Carbon::parse($itemA->activation_date);
                    $now = Carbon::now("Africa/Lagos");

                /*  $dtM = $now->diffInMinutes($tomorrow,false);
              \Log::info('Passing B - with - '.$itemA->user_id.'->diffHours = '.$dtH);
                \Log::info('Passing B - with - '.$itemA->user_id.'->diffMinutes =  '.$dtM);*/



                        if ($now->diffInMinutes($tomorrow, false) <= 1) {
                            $countprofit = DailyLog::where('user_id', $itemA->user_id)->where('plan_type','B')->count();
                            if ($countprofit <= $itemA->plan_days) {
                                $dailylog = new DailyLog();
                                $dailylog->user_id = $itemA->user_id;
                                $dailylog->title = $itemA->plan_name;
                                $dailylog->amount = 0.03 * $itemA->plan_value;
                                $dailylog->type = "Profit";
                                $dailylog->plan_type = "B";
                                $dailylog->date = Carbon::now("Africa/Lagos");
                                $dailylog->save();
                                //New Plans profit date instance created@#af
                                $addDay = $now->addDay()->addHour()->toDateTimeString();
                                Plan::where('user_id', $itemA->user_id)
                                    ->update(array(
                                        'activation_date' => $addDay
                                    ));

                                $checkforuser = businesslog::where('user_id', $itemA->user_id)->where('plan_type','B')->first();
                                if ($checkforuser != null)
                                {
                                    $profitofA = DB::table('businesslogs')->where('user_id', $itemA->user_id)->value('daily_profit');
                                    $profitofB = DB::table('daily_logs')->where('user_id', $itemA->user_id)->where('plan_type', 'B')->latest()->value('amount');
                                    $sum = $profitofA + $profitofB;
                                    businesslog::where('user_id', $itemA->user_id)
                                        ->update(array(

                                            'daily_profit' => $sum
                                        ));
                                }


                            } else {
                                Plan::where('user_id', $itemA->user_id)
                                    ->where('plan_type', 'B')
                                    ->update(array(
                                        'activation_date' => Carbon::create(0000, 0, 0, 0, 0, 0),
                                        'activation' => 0
                                    ));
                            }


                        }






            }


        }






    }


}
