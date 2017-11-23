<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Html\HtmlServiceProvider;
use Illuminate\Database\Eloquent\Model;

use App\Http\Requests;
use App\Booking;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use DB;
use Illuminate\Support\Facades\Redirect;
use Xavrsl\Cas\Facades\Cas;
use Session;
use PDO;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    { 
        //********CAS authentication****
        Cas::authenticate();
        $username=Cas::getCurrentUser();
        
        
        //********Check If user exist in DB*****
        $wherefield="name";
        $val=$this->getUserSingleDetail($wherefield,$username);
        If($val=='')
        {
        return view('booking.register');
        }
        //******To store name in session******
        Session::put('empname', $val);
        $bookings = DB::table('bookings')->where('username', $username)->where('confirmed', '1')->orderBy('date_of_booking', 'desc')->get();
         
        return view('booking.index',['bookings' => $bookings]);
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
        Cas::authenticate();
        $username = Cas::getCurrentUser();
        $bookingInfo = Input::all();
        $booking = new Booking;
        

        // Fetch user details to store with booking date
        $empdetail= $this->getUserDetail();
        foreach ($empdetail as $emp) {
           
            $booking->name=$emp->name;
            $booking->username=$emp->username;
            $booking->email_id=$emp->email;
        }        
        //****Set Booking Date value to store in DB
        $strtime_date = strtotime($bookingInfo['date_of_booking']);
        $booking->date_of_booking = date("Y-m-d",$strtime_date);
        
        //****Set Booking Week value to store in DB
        $week_of_booking = date("W",$strtime_date);
        $booking->week_of_booking = $week_of_booking;
        
        //****Set Booking Day value to store in DB
        $day_of_booking = date("z",$strtime_date);
        $booking->day_of_booking = $day_of_booking;
       
        //****Check if booking for the required date already exists 
        $booking_select = DB::table('bookings')->select('day_of_booking')->where('username', $username)->where('confirmed', '1')->get();
        $array = array();
        foreach ($booking_select as $key => $value) {
            $array[] = ($value->day_of_booking);
        }

        //****Check for: booking day
        $key = array_search(($day_of_booking), $array);
        
        //****Check for: booking week        
        $week_select = DB::table('bookings')->select('week_of_booking')->where('week_of_booking', $week_of_booking)->where('username', $username)->where('confirmed', '1')->count();
        
         //****Check for: holiday booking
        $holiday_select = DB::table('holidays')->select('holiday_date')->where('holiday_date', $booking->date_of_booking)->count();
        
        //****Check for: booking limit is 15 seats only
        $totalseat_select = DB::table('bookings')->select('day_of_booking')->where('day_of_booking', $day_of_booking)->where('confirmed', '1')->count();
        
        // Get current time - Employee can't book ticket for next day after 6pm on current day
        $current_time = date('H:i:s', time()); // UTC Time
        $booking_limit_time = '03:30:00'; // UTC Time for 9:00am
        
        if(!$this->isValidDate($booking->date_of_booking)){//Condition to validate date
            return Redirect::route('booking.index')->with('responseError', 'Please select any valid date.');
        }else if(($booking->date_of_booking === date('Y-m-d') && $current_time >= $booking_limit_time)
                    || ($booking->date_of_booking < date('Y-m-d'))){ //Condition to restrict after 9AM
            return Redirect::route('booking.index')->with('responseInfo', 'Please be informed that we cannot take your booking now. Booking can only be done before 9 AM on the day of your planned visit.');
        }else if(false !== $key){ //Condition to restrict for same day booking
            return Redirect::route('booking.index')->with('responseError', 'Your booking already exists for the day. Please select another date.');
        }else if($week_select >= 2){ //Condition to restrict more than two booking in a week
            return Redirect::route('booking.index')->with('responseError', 'You have exceeded your weekly limit! We will not be able to take your booking for this week. Please try again next week.');
        }else if($totalseat_select >= 15){ //Condition to restrict booking more than 15 seats in a day
            //Added flag(confirmed) value as zero for unfulfilled bookings request
            $bookingflag=0;            
            $booking->confirmed = $bookingflag;
            $booking->save();
            return Redirect::route('booking.index')->with('responseError', 'Seat is not available for this day. Please select another date.');
        }else if($booking->date_of_booking > date('Y-m-d',strtotime('+1 month'))){ // Check for: If booking is beyond 1 month limit from the current date
            return Redirect::route('booking.index')->with('responseError', 'Can\'t book seats more than one month in advance');
        } else if($holiday_select>=1){ //Condition to restrict booking on holidays
            return Redirect::route('booking.index')->with('responseError', 'Seats are not available for holidays');
        }else { //Condition to book valid seat
              
            $booking->seatname=$this->getSeat($totalseat_select,$day_of_booking);
            $booking->save();
            $bookingdate=date('d F Y',strtotime($booking->date_of_booking));
            $subject="Confirmation Message";
            $template='mailtemplate.bookingmail';
            $cc=array('aditi.negi@nagarro.com');
            $obj = new BookingmailController();
            $obj->sendemail($booking->seatname,$bookingdate,$subject,$cc,$template,0,'');
            return Redirect::route('booking.index')->with('responseSuccess', 'New booking added successfully.');
        }
    }

    //Get the seat name
    public function getSeat($totalseats,$day_of_booking1)
    { //Get booked seat arrayfrom booking table
        DB::setFetchMode(PDO::FETCH_ASSOC);        
        $seatvalues = DB::table('bookings')->select('seatname')->where('day_of_booking', $day_of_booking1)->get();
        DB::setFetchMode(PDO::FETCH_CLASS);
           
        $seatarry=[];
        foreach($seatvalues as $seat)
        {
            $seatarry[]=$seat['seatname'];
        
        }
	$seatno= 1;
      if($totalseats>0)
       { $trimedseat= substr($seatarry[0],0, -1);
        $newseatno=$totalseats+1;
           for($i=1;$i<=15;$i++)
           {
            $newseat=$trimedseat.$i;
           if (!in_array($newseat, $seatarry))
           { $seatno=$i;
            break;} 
           }
                    
        }
        //echo  'seatNo::'.$seatno;  
       $seats = DB::table('seats')->where('seatcount', $seatno)->value('seatname');
       return $seats;
    }
    //Get all the details of User
    public function getUserDetail()
    {
         $username=Cas::getCurrentUser();
         $employees = DB::table('employees')->where('username', $username)->get();
         return $employees;
    }
    //Get a perticular detail of User
     public function getUserSingleDetail($wherefield,$username1)
    {
         //$username=Cas::getCurrentUser();
         $singledetail = DB::table('employees')->where('username', $username1)->value($wherefield);
         return $singledetail;
    }

    // Check if date format is correct
    private function isValidDate($date) 
    { 
        $valid = true;
        if(false === strtotime($date) || $date == '1970-01-01' || date('N', strtotime($date)) >= 6){
            $valid = false;   
        }else{ 
            list($year, $month, $day) = explode('-', $date); 
            if (false === checkdate($month, $day, $year)) 
            { 
                $valid = false;
            } 
        } 
        return $valid;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   

    public function SubmitActions()
    {
		
        $selectedHomeOperation = Input::all();
        if($selectedHomeOperation['action'] === 'Cancel') {
            $cancel_booking = Booking::find($selectedHomeOperation['home_id']);
           if(!isset($cancel_booking->date_of_booking)){//To handle the multiple submission by user that creating error.
               return Redirect::route('booking.index');
               }
            //echo $cancel_booking->date_of_booking;
            /*  Cancel booking only if booking date is greater than or equal to current date
                Can't cancel bookings which have date less than current date,
                or if same date, then can't cancel booking for same date after 9am on that day
            */
            
            $current_time = date('H:i:s', time()); // UTC Time
            if ((($cancel_booking->date_of_booking > date('Y-m-d'))
                || ($cancel_booking->date_of_booking == date('Y-m-d') 
                        && $current_time <= '03:30:00'))
                    && (isset($cancel_booking->date_of_booking))) 
                { // UTC Time for 9:00am
                
                $cancel_booking->delete();
            $bookingdate=date('d F Y',strtotime($cancel_booking->date_of_booking));
            $subject="Cancellation Message";
            $template='mailtemplate.cancellationmail';
            $cc=array('aditi.negi@nagarro.com');
            $obj3 = new BookingmailController();
            $obj3->sendemail('',$bookingdate,$subject,$cc,$template,0,'');
            return Redirect::route('booking.index')->with('cancelresponseSuccess', 'Booking cancelled successfully.');
            } else if ($booking->date_of_booking < date('Y-m-d')) { 
                return Redirect::route('booking.index')->with('cancelresponseSuccessError', 'Previous bookings cannot be cancelled.');
            } else {
                return Redirect::route('booking.index')->with('cancelresponseSuccessError', 'Can\'t cancel booking for today after 9am.');
            }
        }
    }
     public function logout()
    {
         Cas::logout();
    }
}