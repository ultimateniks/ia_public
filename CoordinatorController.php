<?php

/**
 * Created by PhpStorm.
 * User: srijanrajput
 * Date: 5/11/2015
 * Time: 1:04 PM
 */
class CoordinatorController extends \BaseController
{ 

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    { 
        $employees = EmployeeProjectMapping::with('employee_rel', 'project_rel')->get();
        $this->layout->content = View::make('coordinator.index', compact('employees'));
    }

    /**
     * Renders the home page for coordinator
     */
    public function renderHome()
    { 
        $this->layout->content = View::make('coordinator.home');
    }

    /**
     * Renders view of party plan for coordinator
     */
    public function planParty()
    { 
        $this->layout->content = View::make('coordinator.plan');
    }

    /**
     * @return array
     * Validation rules for the plan party form
     */
    public function planRules()
    {
        return array(
            'start_date' => 'required|min:5',
            'end_date' => 'required',
            'amount' => 'required|between:0,1000000.00',
            'datepicker' => 'required|date_format:Y-m-d',
            'venue' => 'required|max:500',
            'employee' => 'required',
            //'project_id' => 'required',
        );
    }

    /**
     *
     */
    public function deletePartyData()
    { 
        $party_id = $_POST['party_id'];
        $employee_project_mapping = new EmployeeProjectMapping();
        $party_plan = new PartyPlan();
        $party_details = $party_plan->where('id', '=', $party_id)->first();

        if($party_details->status != 4) {
            
            $mail = new Email();
            $recipients = $this->getPmNMmFromPartyId($party_id);            
            $attendees = User::whereIn('id',
            PartyDetails::where('party_id', '=', $party_id)->lists('employee_id'))->lists('email');
            $extra_attendees_array = explode(',', $party_details->extra_attendees);
            $extra_employees = $this->getEmployeesEmails($extra_attendees_array);
            //$recipients = array_merge($attendees, $extra_employees);
            $admin_id=EmployeeRoleMapping::where('role','=','1')->pluck('employee_id');
            $admin_email=User::where('id','=',$admin_id)->first();
            $cc=$admin_email->email;
            
            $subject = Config::get('params.mail_subjects')['CANCEL_TO_EMPLOYEES'];
            $body = View::make('emails.party_cancelled')
                ->with(array(
                    'date' => $party_details['party_date'],
                    'venue' => $party_details['venue'],
                    'coordinator' => $party_details['coordinator_id']
                ))
                ->render();
            $template='emails.party_cancelled';
            $data=array(
                    'date' => $party_details['party_date'],
                    'venue' => $party_details['venue'],
                    'coordinator' => $party_details['coordinator_id'],
                    'template'=>$template
                    );

            $mail->to=$recipients;
            $mail->cc=$cc;
            $mail->body=serialize($data);
            
            $mail->subject = $subject;
            $mail->type = Config::get('params.mail_types')['CANCEL_TO_EMPLOYEES'];
			$mail->body=serialize($data);
            $mail->save();            
			
       
            //$email->insertMail($recipients, $subject, $body, Config::get('params.mail_types')['PARTY_DELETED'], $cc);
        }
        
        $employee_project_mapping->employeeProjectInRange($party_details->start_date, $party_details->end_date)
            ->whereIn('employee_id', PartyDetails::where('party_id', '=', $party_id)->lists('employee_id'))
            ->whereIn('project_id', PartyProjectMapping::where('party_id', '=', $party_id)->lists('project_id'))
            ->update(array('status' => 0));

        $party_details_delete = PartyDetails::where('party_id', '=', $party_id);
        $party_details_delete->delete();

        $party_project_mapping_delete = PartyProjectMapping::where('party_id', '=', $party_id);
        $party_project_mapping_delete->delete();
        $party_plan->deleteParty($party_id);
	//Queue::push('SendEmails', $data);
        return;
    }

    private function getAllocationDetails($start_date, $end_date, $project)
    {
        $employee_project_mapping = new EmployeeProjectMapping();

        return $allocation_details = $employee_project_mapping->getList($start_date, $end_date, $project);
    }

    private function getBudgetAllocations($allocation_details)
    {
        $detail = array();
        foreach ($allocation_details as $allocation) {
            if (isset($detail[$allocation->project_id])) {
                $detail[$allocation->project_id] += $allocation->budget;
            } else {
                $detail[$allocation->project_id] = $allocation->budget;
            }
        }

        return $detail;
    }

    /**
     * @param array $project_id takes array of project id
     * @param string $start_date
     * @param string $end_date
     * Calculated the total future allocation of budget between start date and end date
     */
    private function getFutureCalculation($project_ids, $start_date, $end_date){
        $total = 0;
        $epm = new EmployeeProjectMapping;
        $startR = $this->getStartDate($start_date);
        $endR = $this->getLastDate($end_date);
        foreach($project_ids as $value){
            $total +=  $epm->futureBudgetCalculation($value, $startR, $endR)->allocated;
        }
        
        return $total;
    }

    private function getStartDate($start_date){
        
        $start = explode(',', $start_date);
        $startR = $start[1] . '-' . $start[0] . '-' . '01';
        
        return $startR;
    }

    private function getLastDate($end_date){
        
        $end = explode(',', $end_date);
        $endR = $end[1] . '-' . $end[0] . '-' . $this->getLastday($end[1], $end[0]);
        
        return $endR;
    }

    /**
     * @param $start_date
     * @param $end_date
     * @param $projects
     * @return null
     */
    private function getCoordinatorProject($start_date, $end_date, $projects){
        
        $epm = new EmployeeProjectMapping();
        $t = $epm->employeeProjectInRange($start_date, $end_date)
            ->select('project_id', DB::raw('count(*) as total'))
            ->whereIn('project_id', $projects)
            ->groupBy('project_id')
            ->take(1)
            ->orderBy('total', 'desc')
            ->get();
        if (sizeof($t) > 0) {
            return Project::where('id', '=', $t[0]->project_id)->first()->programme_manager;
        }

        return null;
    }
    
    //****code added by Aditi START
    
     public function getProjectStartDate($projId1) {
         
        $project = new Project();
        $projectobj = Project::where('id', $projId1)
			//->orderBy('start_date','ASC')
			//->limit(1)
			->lists('start_date');
		return $projectobj[0];
                //var_dump($projectobj);die;
        //$projdate=substr($projectobj[0], 0, 10);
        //$month=date("m",strtotime($projdate));
        //$year=date("Y",strtotime($projdate));
        
        //return $nextmonthofprojectstartdate=$month.','.$year;
        
    }
    
    public function getLastPartyMonth($projId) {
         
         foreach ($projId as $value) {
             $party_plan = new PartyPlan();
        $party_ids = PartyProjectMapping::where('project_id', $value)
            ->lists('party_id');
        // var_dump($party_ids);die;
        
        if(empty($party_ids)){ //When there is no party of project
            
            $start_date[]=$this->getProjectStartDate($value);
			
            }else{  //Month till Last party budget consumed
               
             $PartiesData = $party_plan->whereIn('id',$party_ids)->where('status', '2')
								->orderBy('party_date','DESC')
								->limit(1)
								->lists('consumed_allocation');
               //!!!!!!!!!If there is error as unexpected OffSet then there might be project date not not available
              if(empty($PartiesData)){$start_date[]=$this->getProjectStartDate($value);}else{
             //var_dump($PartiesData);die;
            $start_date[]=$this->getNextMonth($PartiesData[0]);
              }
         }}
         sort($start_date);
       
         return date("m,Y",strtotime($start_date[0]));
          
 
    }
    public function getBudgetEndMonth() {
        
        $todaydate=date('d');
        $currentMonth = date('Y-m-d');
        if($todaydate<=15)
        { 
        $lastMonth = Date('Y-m-d', strtotime($currentMonth . "first day of last month"));
        $end_date = Date('m,Y', strtotime($lastMonth . "first day of last month"));
        }
        else
        {       
        $end_date = Date('m,Y', strtotime($currentMonth . "first day of last month"));
        }  
        
       return $end_date;
    }
    
    public function getPreviousMonth($curDate) {
        
        return Date('Y-m-d', strtotime($curDate ."first day of last month"));
    }
    
    public function getNextMonth($curDate) {
        
        return Date('Y-m-d', strtotime($curDate .'first day of next month'));
    }
    
    public function AddMonthsTillSelectedMonth($partyDate,$lastBudgetMonth) {
        //*****When Current and Next Quareter is NOT selected, 
        //Condtions that cover selected max month from party date calendar *****//
        $calMonth=1;
        
        //****To calculate number of months from party date
         $partyDate = $this->getPreviousMonth($partyDate);
         $PreMonthFromParty=date('m,Y',strtotime($partyDate));
        
        
        if($lastBudgetMonth!=$PreMonthFromParty)//If Month is next to next from selected month
            {$calMonth++;
            $partyDate = $this->getPreviousMonth($partyDate);
            $PreMonthFromParty=date('m,Y',strtotime($partyDate));
            }
           
        if($lastBudgetMonth!=$PreMonthFromParty)//If month is next to selected month
            {$calMonth++;
            $partyDate = $this->getPreviousMonth($partyDate);            
            $PreMonthFromParty=date('m,Y',strtotime($partyDate));
            }
            
        if($lastBudgetMonth!=$PreMonthFromParty)//If month current and selected month is same
            {$calMonth++;
            $partyDate = $this->getPreviousMonth($partyDate);
            $PreMonthFromParty=date('m,Y',strtotime($partyDate));
            }
            return $calMonth;
        
    }
    
    public function SelectedQuarter($currentquarter,$nextquarter,$partydate) {
        $totalmonth=0;
            //*****When Current and Next Quareter is selected
        if($currentquarter == "true")
        {   $selectedmonth=date('m',strtotime($partydate));
            $quarter = new QuarterDetail();
            $LastQuarterMonth=$quarter->getLastMonth($selectedmonth);
            if($LastQuarterMonth == $selectedmonth) //selected month is LM
            {$totalmonth=0;}
            elseif ($LastQuarterMonth-1 == $selectedmonth) //selected month is MM
            {$totalmonth=1;}
            elseif ($LastQuarterMonth-2 == $selectedmonth) //selected month is FM
            {$totalmonth=2;}
            
        if($nextquarter == "true")
        {
            $totalmonth = $totalmonth+3;
                   }
        } 
        return $totalmonth;
        //*****When Current and Next Quareter is selected 
    }
    //****code added by Aditi END
    
    public function getEmployeeList()
    {   //Check For reimbursement pending parties
        // var_dump($_POST['project']);die;
        $proj_status =  DB::table('party_project_mapping')
            ->leftJoin('party_plan', 'party_project_mapping.party_id', '=', 'party_plan.id')
            ->whereIn('party_project_mapping.project_id',$_POST['project'])
            ->whereIN('party_plan.status',array(0, 1, 3, 4, 5))
           // ->lists('party_plan.status','party_plan.id');
        ->lists('party_project_mapping.project_id');
                
                
//                PartyProjectMapping::select(DB::raw('party_project_mapping.project_id AS id'))
//                ->LEFTJOIN('party_plan', 'party_plan.id', '=', 'party_project_mapping.party_id')
//                //->whereIN('party_plan.status',array(0, 1, 3, 4, 5))
//                ->whereIn('party_project_mapping.project_id',$_POST['project']);
//                        ->where(function($query){
//                                $query->where('programme_manager', '=', Auth::user()->id)
//                                    ->orWhere('project_manager', '=', Auth::user()->id);
//                            })
                        //->lists('party_project_mapping.project_id');
           
    
        
        $start_date=$this->getLastPartyMonth($_POST['project']);
      
        $employee_project_mapping = new EmployeeProjectMapping();
        $party_plan = new PartyPlan();
        $party_project_mapping = new PartyProjectMapping();
        
        //*****Get last month to calculate budget
        $end_date=$this->getBudgetEndMonth();
        // echo $start_date.'--'.$end_date;die;
        
          //****IF Start date is grater than END date START
        
        $start = explode(',', $start_date);
        $end = explode(',', $end_date);
        $startD = $start[1] . '-' . $start[0] . '-' . '01';
        $endD = $end[1] . '-' . $end[0] . '-' . $this->getLastday($end[1], $end[0]);
        if (strtotime($startD) > strtotime($endD)) {
          $start_date=$end_date;
        }
        //echo $start_date.'--'.$end_date;die;
        
        //****IF Start date is grater than END date END
       
        $totalselectedMonth=$this->AddMonthsTillSelectedMonth($_POST['partydate'],$end_date);
        $quatermonth=$this->SelectedQuarter($_POST['currentquarter'],$_POST['nextquarter'],$_POST['partydate']);
        $totalmonth= $totalselectedMonth+$quatermonth;
        
        $project = $_POST['project'];
        $detail = array();
        $remaining_budgets_byid = array();
        $remaining = 0;
        
        $allocation_details = $this->getAllocationDetails($start_date, $end_date, $project);
        $remaining_budgets_array = array();
        foreach ($project as $value) { 
            $last_remaining = $party_project_mapping->getLastRemainingBudget($value);
            if (isset($last_remaining) && !empty($last_remaining)) {
                $remaining += $last_remaining;
                $remaining_budgets_array[App::make('BaseController')->getProjectName($value)] = $last_remaining;
                $remaining_budgets_byid[$value]=$last_remaining;
            } else {
                $remaining_budgets_array[App::make('BaseController')->getProjectName($value)] = 00.00;
                $remaining_budgets_byid[$value]= 00.00;
            }
        }
        $budget_allocations = $this->getBudgetAllocations($allocation_details);
        
        
        foreach($budget_allocations as $key => $value){
            $budget_allocations[App::make('BaseController')->getProjectName($key)] =  $budget_allocations[$key];
            unset($budget_allocations[$key]);
        }
        
        $detail['budget_allocations'] = $budget_allocations;
        $detail['remaining_budgets_array'] = $remaining_budgets_array;
        
        $detail['allocation_details'] = $allocation_details;
        $detail['remaining_budget'] = $remaining;
        $detail['future_budget'] = $this->getFutureCalculation($project, $start_date, $end_date);
        
        $detail['future_months'] = $this->getNoOfMonths($this->getStartDate($start_date),$this->getLastDate($end_date));
        $detail['approver'] = $this->getCoordinatorProject($this->getStartDate($start_date),$this->getLastDate($end_date),$project);
        $detail['start_date']=$start_date;
        $detail['end_date']=$end_date;
        
        //To calculate future budget
        $fb_allocation_details = $this->getAllocationDetails($end_date, $end_date, $project);
        $fb_allocations = $this->getBudgetAllocations($fb_allocation_details);
        $detail['fb_allocation_details'] = $fb_allocation_details;
        //ALSO IF NO CURRENT ALLOCATION FOR SOME PROJECT
         foreach ($project as $pkey => $pvalue) 
            { 
             if (!array_key_exists($pvalue, $fb_allocations)) 
                { 
                  $fb_allocations[App::make('BaseController')->getProjectName($pvalue)]=0;
                  $fb_proj_allocations[$pvalue]= 0;
                  
                }
            else{    
                  $fb_allocations[App::make('BaseController')->getProjectName($pvalue)] = $fb_allocations[$pvalue]*($totalmonth);
                  $fb_proj_allocations[$pvalue]=  $fb_allocations[$pvalue]*($totalmonth);
                            
                }
                unset($fb_allocations[$pvalue]);
            }
        
        if(!empty($proj_status)){
             $detail['proj_status']=0;
             foreach ($proj_status as $pky => $pval) 
                 {
                 $reimbursement_pending_proj[]=App::make('BaseController')->getProjectName($pval);
                 }
        //var_dump($proj_status);die;
        $detail['reimbursement_pending_proj']=implode(",",$reimbursement_pending_proj);
    }else{$detail['proj_status']=1;}
            
        $detail['totalmonth']=$totalmonth;
        $detail['fb_allocations'] = $fb_allocations;
        $detail['fb_proj_allocations']=$fb_proj_allocations;
        //$detail['remaining_budgets_byid']=$remaining_budgets_byid;
        //var_dump($fb_proj_allocations);
        //var_dump($remaining_budgets_byid);
        //$new_array=array();
        //array_push($new_array,$fb_proj_allocations,$remaining_budgets_byid); 
        //print_r($new_array);
        //var_dump(array_merge($fb_proj_allocations,$remaining_budgets_byid));
        
        $detail['remaining_budgets_byid']=$remaining_budgets_byid;
        
        
        return $detail;
    }

    private function getLastday($year, $month)
    {
        $date1 = $year . '-' . $month;
        $d = date_create_from_format('Y-m', $date1);
        $last_day = date_format($d, 't');

        return $last_day;
    }

    public function planned()
    { 
        $this->layout->content = View::make('coordinator.planned');
    }

    public function reimburseForm()
    {
        $party_id = $_POST['party_id'];

        return View::make('coordinator.reimburse_form')->with(compact('party_id'));
    }

    public function viewPlanned()
    {
        $party_id = $_POST['party_id'];

        return View::make('coordinator.view_planned')->with(compact('party_id'));
    }

    public function coordinatorPlanned()
    {
        $this->layout->content = View::make('coordinator.coordinator_planned');
    }

    /**
     * downloads the file address given the path variable
     * @return download response
     */
    public function download()
    {

        $file = $_GET['path'];
        $file_path = Config::get('params.destination_path') . '/' . $file;

        return Response::download($file_path);
    }

    /**
     * Upload the reimbursement bill and update the path in database
     * @return view
     */
    public function insertReimburseRequest()
    {
        $all = Input::all();
        $id = $all['party_id'];
        $project_id = $all['project_id'];
        $start_date = $all['start_date'];
        $end_date = $all['end_date'];
        $file = Input::file('file');
        $spent_amount = $all['spent_amount'];
        $extension = $file->getClientOriginalExtension();
        $destinationPath = Config::get('params.destination_path');
        $project_name = Project::where('id', '=', $project_id)->pluck('name');
        $filename = $project_name . '_' . $start_date . ' to ' . $end_date . '_' . Auth::User()->username . '_' . time() . '.' . $extension; // If the uploads fail due to file system, you can try doing public_path().'/uploads'
        $upload_success = Input::file('file')->move($destinationPath, $filename);

        if ($upload_success) {
            PartyPlan::where('id', '=', $id)->update(array(
                'status' => '1',
                'spent_amount' => $spent_amount,
                'bill_path' => $filename,
                'request_on' => date('y-m-d')
            ));
        $admin_id=EmployeeRoleMapping::where('role','=','1')->pluck('employee_id');
        $admin_mail=User::where('id','=',$admin_id)->pluck('email');
            /*Email code to be sent to admin for reimbursement request*/
        $email = new Email();
        $email->to=$admin_mail;
        // $email->cc=$admin_mail;
        $email->subject = Config::get('params.mail_subjects')['REQUEST_TO_ADMIN'];
        // $email->type = Config::get('params.mail_types')['PLAN_TO_EMPLOYEES'];
        $email->save();
        $template='emails.reimbursement_requested';
		
        $data=array(
            'party_id'=>$id,
            'template'=>$template,
			'coordinator'=>Auth::user()->id,
        );
		$email->body=serialize($data);
        $email->save();

        //Queue::push('SendEmails', $data);
            // $recipients = [Config::get('params.party_dl')];
            // $cc = [Config::get('params.party_hr')];
            // $subject = Config::get('params.mail_subjects')['REQUEST_TO_ADMIN'];
            // $body = View::make('emails.reimbursement_requested')
            //     ->with(array(
            //         'party_id' => $id
            //     ))
            //     ->render();
            // $email->insertMail($recipients, $subject, $body, Config::get('params.mail_types')['REQUEST_TO_ADMIN'], $cc);
//             Response::json('success', 200);
            return Redirect::route('project_manager.planned')->with('success', 'Reimburse request sent successfully !');

        } else {
            return Response::json('error', 400);
        }

        return Redirect::back()->with('error', 'No request raised');

    }

    /**
     * @return mixed
     * Saves the party plan managed by project manager
     */
    public function store()
    { 
        //echo Auth::user()->role;die;
        $all = Input::all();
        // echo '<pre>';
         
        // var_dump($all);die;
        $project_id = Input::get('project_id');
        $validator = Validator::make(Input::all(), $this->planRules());
        //var_dump($validator);die;
        if ($validator->passes()) {            
            $proj_fb = Input::get('proj_fb');
            $proj_rb = Input::get('proj_rb');
            //$proj_fb1=0-$proj_fb[22];die;
            //var_dump($validator);die;
            $party_plan = new PartyPlan();
            $party_plan->coordinator_id = Auth::user()->id;
//          $party_plan->project_id = implode(',', Input::get('project_id'));
            $start_date = Input::get('start_date');
            $end_date = Input::get('end_date');
            $end_date_array = explode(',', $end_date);
            $current_date_array = explode(',', date('m,Y'));
            $start = explode(',', $start_date);
            $party_date =  Input::get('datepicker');
            $location = Input::get('venue');
            $extra_attendees = array();
            
            if (isset($_POST['extra_attendees'])) {
                $extra_attendees = Input::get('extra_attendees');
                $extra_attendees_mails = User::whereIn('id', $extra_attendees)->lists('email');
            }
            $end = explode(',', $end_date);
            $startR = $start[1] . '-' . $start[0] . '-' . '01';
            $endR = $end[1] . '-' . $end[0] . '-' . $this->getLastday($end[1], $end[0]);
            list($m,$y)=explode(',', $end_date);
            $con_allocation=$y."-".$m."-01";            
            $party_plan->consumed_allocation=$con_allocation;
            $party_plan->plan_amount = Input::get('amount');
            $party_plan->venue = $location;
            $party_plan->party_date = $party_date;
            $party_plan->start_date = $startR;
            //echo (int)$end_date_array[1].Auth::user()->role;die;
            if ((Auth::user()->role != 'project_manager' || Auth::user()->role != 'mm') && (((int)$end_date_array[1] > (int)$current_date_array[1]) || ((int)$end_date_array[1] == (int)$current_date_array[1] && (int)$end_date_array[0] > (int)$current_date_array[0]))) {
                $party_plan->status = 4;
                $party_plan->approve_by_mm = 1;
                //$party_plan->future_budget = Input::get('future_budget');
                $party_plan->future_months = Input::get('future_months');
                $party_plan->approver = Input::get('approver');
            } elseif (((int)$end_date_array[1] > (int)$current_date_array[1]) || ((int)$end_date_array[1] == (int)$current_date_array[1] && (int)$end_date_array[0] > (int)$current_date_array[0])) {
                $party_plan->status = 1;
                $party_plan->approve_by_mm = 0;
                //$party_plan->future_budget = Input::get('future_budget');
                $party_plan->future_months = Input::get('future_months');
                $party_plan->approver = Input::get('approver');

            }
                $party_plan->end_date = $endR;
            if (isset($_POST['extra_attendees'])) {
                $party_plan->extra_attendees = implode(",", $extra_attendees);
            } 
            
            $party_plan->save();
            $epm = new EmployeeProjectMapping();
            
                  //$proj_fb = Input::get('proj_fb');
            $allocation_details = $this->getAllocationDetails($start_date, $end_date, $project_id);
            $budget_allocations = $this->getBudgetAllocations($allocation_details);
            //var_dump($budget_allocations);exit;
            foreach ($budget_allocations as $key => $value) {
                 try{
                $party_project_mapping = new PartyProjectMapping();
                $party_project_mapping->party_id = $party_plan->id;
                $party_project_mapping->project_id = $key;
                $party_project_mapping->budget_amount = $value;
                $party_project_mapping->remaining_amount =  $proj_rb[$key];
                //-$proj_fb[$key];
                $party_project_mapping->future_budget = $proj_fb[$key];
                $party_project_mapping->save(); // returns false
                    }
                    catch(Exception $e){
                       // do task when error
                       echo $e->getMessage();   // insert query
                    }
                
            }
            try{            
                $epm = new EmployeeProjectMapping();
                $epm->employeeProjectInRange($startR, $endR)
                    ->whereIn('project_id', $project_id)
                        ->where('hours','>=',40)
                    ->update(array('status' => 1));
                }
                    catch(Exception $e){
                       // do task when error
                       echo $e->getMessage();   // insert query
                    }
            $employees = Input::get('employee');
            $month_ids = Input::get('months');
            foreach ($employees as $employee) {
                $employee_detail = User::where('id', '=', $employee)->first();

                $party_details = new PartyDetails();
                $party_details->party_id = $party_plan->id;
                $party_details->employee_id = $employee;
                $party_details->party_months = $month_ids[$employee];
                $party_details->save();
                $employee_project_mapping = new EmployeeProjectMapping();
                $employee_months = $month_ids[$employee];
                $employee_months_array = explode(';', $employee_months);
                

            }
            if (Auth::user()->role != 'mm' && (((int)$end_date_array[1] > (int)$current_date_array[1]) || ((int)$end_date_array[1] == (int)$current_date_array[1] && (int)$end_date_array[0] > (int)$current_date_array[0]))) {
                $mail = new Email();
                $extra_attendees = Input::get('extra_attendees');
                $mm_pm_ids = $this->getPmNMmFromPartyId($party_plan->id);
                $approver = Input::get('approver');
                $future_budget = Input::get('future_budget');
                $full_name = User::where('id', '=', $approver)->pluck('name');
                $approve_email = User::where('id', '=', $approver)->pluck('email');
                $name = explode(" ", $full_name);
                $first_name = $name[0];
                $duration = \Helpers\Helper::getMonthYear($startR).' to '.\Helpers\Helper::getMonthYear($endR);
                $projects = BaseController::getProjectFromPartyId($party_plan->id);
                $mail->to = $approve_email;
//                $mail->cc = $mm_pm_ids;
                $mail->cc = User::where('id', '=', Auth::user()->id)->pluck('email');
                $mail->subject = Config::get('params.mail_subjects')['MM_APPROVAL'];
                $mail->body = View::make('emails.mm_approval')
                    ->with(array(
                        'name' => $first_name,
                        'duration' => $duration,
                        'project' => $projects,
                        'future_budget' => $future_budget
                    ));
                $mail->type = Config::get('params.mail_types')['MM_APPROVAL'];
                $mail->save();
            }else {
		$admin_id=EmployeeRoleMapping::where('role','=','1')->pluck('employee_id');
                $admin_mail=User::where('id','=',$admin_id)->pluck('email');
                $mail = new Email();
                
                $pm=Project::whereIn('id',$project_id)->distinct()->lists('project_manager');
                $dm=Project::whereIn('id',$project_id)->distinct()->lists('programme_manager');
                
                $pm_list=implode(',',$pm);
                $dm_list=implode(',',$dm);
                $to_list=$pm_list.','.$dm_list;
                $to_list_array=explode(',', $to_list);
               

                $project_name=Project::whereIn('id',$project_id)->lists('name');
		        $client_id=Project::whereIn('id',$project_id)->distinct()->lists('client');
		        $client_name=Client::whereIn('id',$client_id)->distinct()->lists('name');
                
		$client_project='';
                foreach ($project_id as $key => $value) {
                    if(strlen($client_project) != 0){
                        $client_project=$client_project.','.BaseController::getProjectClient($value);                        
                    }else{
                        $client_project=$client_project.BaseController::getProjectClient($value);
                    }

                }
                
            	$data=array(
                        'date' => Input::get('datepicker'),
                        'venue' => Input::get('venue'),
                        'coordinator' => Auth::user()->id,
                        'client_project'=>$client_project,
                        'template'=>'emails.plan'
                    );
                $mail->body=serialize($data);

                // var_dump($mail);die;
                // echo "string"; die;
            	
                $to_mail_id=$this->getEmployeesEmails($to_list_array);
                $mail_send_to=implode(',',$to_mail_id);
                $mail->to=$mail_send_to;
                $mail->cc=$admin_mail;
                // $mail->body=$data;
        	    $mail->subject = Config::get('params.mail_subjects')['PLAN_TO_EMPLOYEES'];
	            $mail->type = Config::get('params.mail_types')['PLAN_TO_EMPLOYEES'];
                
                $mail->save();
                //Queue::push('SendEmails', $data);
            }
            //return Redirect::back()->with('success',
              //  "Party Planned Successfully! See <a href = '" . route('project_manager.planned') . "'>Planned Parties</a>");
			   return Redirect::route('project_manager.planned')->with('success', 'Party planned successfully!!');

        }

        return Redirect::back()
            ->withInput()
            ->withErrors($validator)
            ->with('message', 'There were validation errors.');
    }

    /**
     * @param array $employee_ids containing integer values of employee ids
     * @return array of emails corresponding to employee ids
     */
    private function getEmployeesEmails($employee_ids){
        // echo $employee_ids;die;
        // echo gettype($employee_ids);die;
       return User::whereIn('id', $employee_ids)->lists('email');
    }

    /**
     * @param array $arr
     * @return string by omitting spaces
     */
    private function arrayToString($arr,$delim){
       return str_replace(' ', '', implode($delim, $arr));
    }

    /**
     * @param integer $party_id
     * @return array of email id of all the project managers and programme managers included in party
     */
    private function getPmNMmFromPartyId($party_id){
        $all_projects = PartyProjectMapping::where('party_id', '=', $party_id)->lists('project_id');
        $all = Project::whereIn('id', $all_projects)->lists('project_manager', 'programme_manager');
        $keys = array_keys($all) ;
        $values = array_values($all) ;
        $ids = array_merge($keys, $values);
        return $this->arrayToString(User::whereIn('id', $ids)->lists('email'),',');
    }
    /**
     * @param integer $party_id
     * @return array of email id of all the project managers and programme managers included in party
     */
    private function getMmFromPartyId($party_id){
        $all_projects = PartyProjectMapping::where('party_id', '=', $party_id)->lists('project_id');
        $all = Project::whereIn('id', $all_projects)->lists('programme_manager');
        return User::whereIn('id', $all)->lists('email');
    }


    private function getCalenderInvite($party_date, $location){
        $vCalendar = new \Eluceo\iCal\Component\Calendar('www.example.com');
        $vEvent = new \Eluceo\iCal\Component\Event();
        $vEvent
            ->setDtStart(new \DateTime($party_date))
//            ->setDtEnd(new \DateTime('2015-07-29'.' 23:59:00'))
            ->setNoTime(true)
            ->setSummary('Party')
            ->setLocation($location)
        ;
        $vCalendar->addComponent($vEvent);
//        header('Content-Type: text/calendar; charset=utf-8');
//        header('Content-Disposition: attachment; filename="cal.ics"');
        $filename = 'plan'.time().'.ics';
        $filepath = Config::get('params.calendar_path');
        $file = storage_path().'\\'.$filepath.'\\'.$filename;
        $data = $vCalendar->render();
        file_put_contents($file, $data);
        return $filename;

    }
}
