<?php

namespace App\Http\Controllers;

use App\Admin;
use App\Article;
use App\Customer;
use App\Plan;
use App\referral;
use App\Ticket;
use App\User;
use App\withdrawLog;
use function compact;
use function htmlentities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use App\Message;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use function redirect;
use function ucfirst;
use function view;
use App\Exceptions\NotFoundHttpException;
use Carbon\Carbon;
use function explode;
use Illuminate\Support\Facades\Session;
use function trim;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware("auth:admin");
    }

    //DISPLAY HOME PAGE TO ADMIN ---------------------------------------------1>
    public function index()
    {

        return view("admin.dashboard");
    }

    //DISPLAY ADMIN MESSAGE SENDER ---------------------------------------------2>
    public function messageComposer()
    {
        return view("admin.composer");
    }
    public function showPostComposer()
    {
        return view("admin.createpost");
    }

    public function showUserActivation()
    {
        return view("admin.activation");
    }


    //DISPLAY USER SENT TICKET TO ADMIN----------------------------------------------3>
    public function ticket()
    {
        $tck = Ticket::orderBy("created_at","desc")->paginate(10);
        return view("admin.tickets.notification",compact("tck"));
    }

    //SHOW READ MORE FOR POSTED INFORMATION ------------------------------------------4>
    public function showPostInbox($id)
    {

        $id_de = Crypt::decrypt($id);
        $article = Article::findOrFail($id_de);
        return view("feeds.inbox.postBox", compact("article"));

    }
    //DISPLAY USERS WHO MADE A PAYOUT --------------------------------------------->
    public function payment()
    {
        $wtdLog = DB::table("withdraw_logs")->orderBy("transfer_date","DESC")->where("status","Pending")->get();
        return view("admin.payment",compact("wtdLog"));
    }

    //PAYMENT ALART SYSTEM -------------------------------------------->
    public function paymentAlart(Request $request)
    {
        $rules = [
            "withdrawalCode" => "required",
            "tokenCode" => "required"



        ];
        $input = Input::only(
            "withdrawalCode",
            "tokenCode"
        );
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return redirect("admin/payment")
                ->withInput()
                ->withErrors($validator);
        }
        //Looping through list of users posted in the username field

        $checkTokenCode = Admin::where("tokenCode",$request->tokenCode)->exists();
        if ($checkTokenCode != null)
        {
            $user_input = Input::get("withdrawalCode");
            $array_list = explode(",",$user_input);

            foreach ($array_list as  $lists)
            {
                $each_code = $lists;
                echo $each_code;
                $get_user_name = withdrawLog::where("wthCode",$each_code)->first();
                if ($get_user_name === null)
                {

                    Session::flash("flash","No withdrawal record found for this transaction ->".$each_code);
                    continue;
                }
                $checkStatus = withdrawLog::where("wthCode",$each_code)->where("status","Pending")->first();
                if ($checkStatus != null)
                {

                    withdrawLog::where("wthCode",$each_code)->update(array(
                        "status" => "Paid",

                    ));

                }



            }
            $notification = array(
                "head" => "Payment Alert sent",
                "message" => "All Payment updates sent",
                "alert-type" => "success"
            );
            return redirect("admin/payment")->with($notification);
        }
        $notification = array(
            "head" => "Payment Alert Failed",
            "message" => "Wrong Admin Token Code",
            "alert-type" => "danger"
        );
        return redirect("admin/payment")->with($notification);




    }

    /*-------------------------------- Sending broadcast to registered user-------------------------------------------*/

    public function messageSender(Request $request)
    {

        $users = User::all()->except(Auth::id());
        $rules = [
            "title" => "required",
            "message" => "required"
        ];
        $input = Input::only(
            "title",
            "message"
        );
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return redirect("admin/composer")
                ->withInput()
                ->withErrors($validator);
        }
        foreach($users as $user){
            $messages = new Message();
            $messages->user_id = $user->id;
            $messages->sender = Auth::user()->username;
            $messages->title = Input::get("title");
            $messages->content = htmlentities(Input::get("message"));
            $messages->save();
        }
        echo "success";
        $notification = array(
            "message" => "Message Successful. Your Message has been sent. Thank you",
            "alert-type" => "success"
        );
        return redirect("admin/composer")->with($notification);

    }



        /*-----------------------Activation of registered User at once-------------------------*/
    public function activateUsers(Request $request)
    {
        $rules = [
            "username" => "required",
            "plan_name" => "required",
            "activate" => "required"


        ];
        $input = Input::only(
            "username",
            "plan_name",
            "plan_value",
            "activate"
        );
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return redirect("admin/user-activation")
                ->withInput()
                ->withErrors($validator);
        }
        //Looping through list of users posted in the username field
        $user_input = Input::get("username");
        $array_list = explode("","",$user_input);

        foreach ($array_list as  $lists)
        {
            $each_user = trim($lists);

            $get_user_name = User::where("username",$each_user)->value("username");
            if ($get_user_name === null)
            {

                Session::flash("flash","This Username ->".$each_user." record does not exist");
                continue;
            }
            //Check the Plans table if user_id from users table is available
            $user_id = Plan::where("activation",0)->where("user_id",$get_user_name)->first();
            if ($user_id === null)
            {

                Session::flash("flash","This user =>".$each_user." has an activated plan already");
                continue;
            }

            Plan::where("activation",0)->where("user_id",$get_user_name)->update(array(
                "activation" => (boolean)$request->activate,
                "activation_date" => Carbon::now("Africa/Lagos")->addHours(24)
            ));

            //Set referral reward price
            $getFname = Customer::where("username",$each_user)->value("firstName");
            $getSname = Customer::where("username",$each_user)->value("lastName");
            $rPrice = 3000;

            $checkIfReferralExist = referral::where("referred_username",$each_user)->first();
            if ($checkIfReferralExist != null)
            {

                referral::where("referred_username",$each_user)->update(array(
                    "referred_firstname" => $getFname,
                    "referred_lastname" => $getSname,
                    "referred_price" => $rPrice,

                ));

            }
        }
        $notification = array(
            "head" => "Account Activated",
            "message" => "Your account has been activated successfully!!! Thank you",
            "alert-type" => "success"
        );
        return redirect("admin/user-activation")->with($notification);



    }

    /*--------------------Sending daily post to all registered users---------------------------------*/
    public function adminPostSender(Request $request)
    {

        if ($request->hasFile("avatar"))
        {
            $filename = $request->file("avatar")->getClientOriginalName();

            $filesize = $request->file("avatar")->getClientSize();
            if ($filesize > 150000)
            {
                $rules = [
                    "title" => "required",
                    "message" => "required",
                    "avatar" => "required | mimes:jpeg,jpg,png | max:150000"

                ];

                $input = Input::only(
                    "title",
                    "message",
                    "avatar"
                );

                $validator = Validator::make($input, $rules);
                if ($validator->fails()) {
                    return redirect("admin/post")
                        ->withInput()
                        ->withErrors($validator);
                }

                $request->file("avatar")->storeAs("public/post",$filename);
                $article = new Article();
                $article->title = "<strong>".Input::get("title")."</strong>";
                $article->sender = ucfirst(Auth::user()->username);
                $article->content = Input::get("message");
                $article->article_img = $filename;
                $article->save();
                copy("/home/overtng/BDONetwork/storage/app/public/post/".$filename,
                    "/home/overtng/public_html/storage/post/".$filename);


                if ($article->save())
                    $notification = array(
                        "head" => "Post Created",
                        "message" => "Your post was uploaded successfully!!! Thank you",
                        "alert-type" => "success"
                    );
                return redirect("admin/post")->with($notification);

            }
            return redirect("admin/post")->withErrors("File size too large. File size should be less than 20Kb");

        }
        return redirect("admin/post")->withErrors("No file selected");

    }

    /*Show ticket and mark it as read*/
    public function showTicket($id)
    {
        $id_de = Crypt::decrypt($id);
        //mark as read
        Ticket::where("ticketNumber",$id_de)->update([
            "status" => true
        ]);

        $article = Ticket::findOrFail($id_de);
        return view("admin.tickets.inbox.messages", compact("article"));
    }

    
    public function showTicketSorting($id)
    {
        $trim = trim($id);
        $tck = Ticket::where("subject",$trim)->paginate(10);
        return view("admin.tickets.notification",compact("tck"));


    }


}
