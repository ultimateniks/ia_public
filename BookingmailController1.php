<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

//Includes
use Illuminate\Html\HtmlServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use DB;
use Illuminate\Support\Facades\Redirect;
use Xavrsl\Cas\Facades\Cas;
use Mail;
use Excel;
use PDO;
use File;


class BookingmailController extends BookingController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Cas::authenticate();
        //This is for cron mail
        //
        $template='mailtemplate.cronmail';
        $todaydate=date('Y-m-d');;
        $nday = date('l', strtotime($todaydate));
        
            
        //****code to fetch next day date or monday date START****
        if($nday=='Friday')
        { $nextdaydate=date('Y-m-d', strtotime(' +3 day'));
          $nday = date('l', strtotime($nextdaydate));
        }
        else
        { $nextdaydate=date('Y-m-d', strtotime(' +1 day'));}
        //****code to fetch next day date or monday date END****
        
		$nextdaydate=date('Y-m-d'); // To send report in morning
		
        //***Sending Daily report for same day
        $subject='Noida Employee Booking List';
        $cc=array('attendance@nagarro.com','sanam.zaman@nagarro.com','aditi.negi@nagarro.com');
       
        // ********Execute the query used to retrieve the data.*****
        DB::setFetchMode(PDO::FETCH_ASSOC);
//        $dbdata = DB::table('bookings')->select(array('name','username','email_id', 'date_of_booking','confirmed'))
//                ->whereBetween('date_of_booking', array($fday, $lday))->get();
        $dbdata = DB::table('bookings')->select(array('name','username','email_id', 'date_of_booking','confirmed'))
                ->where('date_of_booking', $nextdaydate)->get();
        DB::setFetchMode(PDO::FETCH_CLASS);
//        echo '<pre>';
//        print_r($dbdata);die;
        
        // *******Initialize the array which will be passed into the Excel****
        $dbdataArray = []; 

        //********** Define the Excel spreadsheet headers********
        $dbdataArray[] = ['Employee Name', 'Email','Emp Code','Date of visit','IsConfirmed'];

        //********* Convert each member of the returned collection into an array,
        
        $wherefield="empcode";
        foreach ($dbdata as $exldata) {
           // echo $exldata['username'];
        //*********** To Fetch Emp Code
        $empcode=$this->getUserSingleDetail($wherefield,$exldata['username']);
        $Conval = ($exldata['confirmed']==1) ? 'Yes' : 'No';
        $dbdataArray[] = [$exldata['name'], $exldata['email_id'],$empcode,$exldata['date_of_booking'],$Conval];
        }
        //echo '<pre>';
        //print_r($dbdataArray);die;
        //*****Generating Excel file start******
        $fileName = 'Dailybookinglist';
        Excel::create($fileName, function($excel) use ($dbdataArray) {

        // Set the title
        $excel->setTitle('Booking Report');

        // Chain the setters
        $excel->setCreator('Me')->setCompany('Employee Detail');

        $excel->setDescription('List of the employees who booked seats');

        // Build the spreadsheet, passing in the payments array
        $excel->sheet('Sheet 1', function ($sheet) use ($dbdataArray) {
                $sheet->setOrientation('landscape');
                $sheet->fromArray($dbdataArray, NULL, 'A0','false','false');
            });
        // *******Save the file.******
                      })->save('xlsx', storage_path().DIRECTORY_SEPARATOR.'attachment');
        
        //*****Generating Excel file end********
                      
        $pathToFile=storage_path().DIRECTORY_SEPARATOR.'attachment'.DIRECTORY_SEPARATOR.$fileName.".xlsx";
        $this->sendemail('',$nextdaydate,$subject,$cc,$template,1,$pathToFile);
    
        
    }
    public function monthlyreport()
    {
        
        //Cas::authenticate();
        //This is for cron mail
        //
        $template='mailtemplate.monthlyreport';
        $todaydate=date('Y-m-d');;
        $nday = date('l', strtotime($todaydate));
        
        //****Code to fetch report for previous month
        $fday=date('y-m-d',strtotime('first day of last month'));
        $lday=date('y-m-d',strtotime('last day of last month'));
        $lastmonth=date('F',strtotime('last month'));
               
        //***Sending monthly report @ 1st of every month
        $subject='Noida Employee Booking List For '.$lastmonth;
        $cc=array('attendance@nagarro.com','sanam.zaman@nagarro.com','aditi.negi@nagarro.com');
       
        // ********Execute the query used to retrieve the data.*****
        DB::setFetchMode(PDO::FETCH_ASSOC);
        $dbdata = DB::table('bookings')->select(array('name','username','email_id', 'date_of_booking','confirmed'))
                ->whereBetween('date_of_booking', array($fday, $lday))->get();
        
        DB::setFetchMode(PDO::FETCH_CLASS);
        
        // *******Initialize the array which will be passed into the Excel****
        $dbdataArray = []; 

        //********** Define the Excel spreadsheet headers********
        $dbdataArray[] = ['Employee Name', 'Email','Emp Code','Date of visit','IsConfirmed'];

        //********* Convert each member of the returned collection into an array,
        
        $wherefield="empcode";
        foreach ($dbdata as $exldata) {
           // echo $exldata['username'];
        //*********** To Fetch Emp Code
        $empcode=$this->getUserSingleDetail($wherefield,$exldata['username']);
        $Conval = ($exldata['confirmed']==1) ? 'Yes' : 'No';
        $dbdataArray[] = [$exldata['name'], $exldata['email_id'],$empcode,$exldata['date_of_booking'],$Conval];
        }
        
        //*****Generating Excel file start******
        $fileName = 'monthlybookinglist';
        Excel::create($fileName, function($excel) use ($dbdataArray) {

        // Set the title
        $excel->setTitle('Booking Monthly Report');

        // Chain the setters
        $excel->setCreator('Me')->setCompany('Employee Detail');

        $excel->setDescription('List of the employees who booked seats');

        // Build the spreadsheet, passing in the payments array
        $excel->sheet('Sheet 1', function ($sheet) use ($dbdataArray) {
                $sheet->setOrientation('landscape');
                $sheet->fromArray($dbdataArray, NULL, 'A0','false','false');
            });
        // *******Save the file.******
                      })->save('xlsx', storage_path().DIRECTORY_SEPARATOR.'attachment');
        
        //*****Generating Excel file end********
                      
        $pathToFile=storage_path().DIRECTORY_SEPARATOR.'attachment'.DIRECTORY_SEPARATOR.$fileName.".xlsx";
        $this->sendemail('',$lastmonth,$subject,$cc,$template,2,$pathToFile);        
    }
    public function sendemail($seatlocation,$bookingdate,$subject,$cc,$template,$attachment,$pathToFile)
    {
       //echo '</br>'.$bookingdate.$subject.$cc.$template.$attachment.$pathToFile;
        //echo $pathToFile;die;
        //Cas::authenticate();
        //$empdetail= $this->getUserDetail();
       
       
       
        $timestamp = strtotime($bookingdate);
        $day = date('l', $timestamp);
        
     
           if($attachment == 1)
                {
                    $data = array(
                    'bookingdate' => $bookingdate,
                    'day' => $day,
                              );
                    //***Email of HR or any other employee to whom daily report has to send****
                    $to=array('noida.ops@nagarro.com');
                    Mail::send($template, $data, function ($m) use ($subject,$pathToFile,$to,$cc){
                        $m->attach($pathToFile);
                        $m->from('noreply@nagarro.com', 'No Reply');	
                        $m->to($to)->subject($subject)->cc($cc);
	        });
                echo 'Daily Report Sent!';
                }else if($attachment == 2)
                {
                         $data = array(
                    'bookingdate' => $bookingdate,
                    'day' => $day,
                              );
                    //***Email of HR or any other employee to whom monthly report has to send****
                    $to=array('noida.ops@nagarro.com');
                    Mail::send($template, $data, function ($m) use ($subject,$pathToFile,$to,$cc){
                        $m->attach($pathToFile);
                        $m->from('noreply@nagarro.com', 'No Reply');	
                        $m->to($to)->subject($subject)->cc($cc);
	        });
                echo 'Monthly Report Sent!';
                }
          else  {   
		  Cas::authenticate();
                    //********Fetching USER detail to send mail to perticular user******
                    $obj1 = new BookingController();
                    $users = $obj1->getUserDetail();
                    foreach ($users as $user) {
                          $firstname=$user->name;
                          $useremail=$user->email;
                          $cc=$user->project_manager;
                        }
                        list($f)=explode(" ", $firstname);
                    $data = array(
                    'name' => $f,
                    'bookingdate' => $bookingdate,
                        'seatname'=>$seatlocation,
                    'day' => $day,
                              );
                   
                    Mail::queue($template, $data, function ($m) use ($user,$subject,$cc){
	            $m->from('noreply@nagarro.com', 'No Reply');	
	            $m->to($user->email, $user->name)->subject($subject)->cc($cc);
	        });

                }  
              
         
   
        
     return TRUE;
    
    }
    public function update(Request $request, $id)
    {
        //
    }

   
}
