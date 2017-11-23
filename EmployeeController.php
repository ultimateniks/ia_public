<?php

class EmployeeController extends \BaseController {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
//        $employees = User::all();
        $employees = EmployeeProjectMapping::with('employee_rel', 'project_rel')->get();
        $this->layout->content = View::make('employee.index', compact('employees'));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		$this->layout->content = View::make('employee.create');
	}

	public function sendMail(){
        $vCalendar = new \Eluceo\iCal\Component\Calendar('www.example.com');
        $vEvent = new \Eluceo\iCal\Component\Event();
        $vEvent
            ->setDtStart(new \DateTime('2012-12-24'))
            ->setDtEnd(new \DateTime('2012-12-24'))
            ->setNoTime(true)
            ->setSummary('Christmas')
            ->setLocation('ISBT')
        ;
        $vCalendar->addComponent($vEvent);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="cal.ics"');
        echo $vCalendar->render();

        exit;
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
        $input = Input::all();
        $validation = Validator::make($input, User::$rules);

        if ($validation->passes())
        {
            User::create($input);

            return Redirect::route('employee.index');
        }

        return Redirect::route('manager.employees.create')
            ->withInput()
            ->withErrors($validation)
            ->with('message', 'There were validation errors.');

    }


	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		//
	}


	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		//
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		//
	}


	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		//
	}

    /**
     *
     */
    public function renderBudget(){
        $employee_project_mapping = new EmployeeProjectMapping();
        $id = Auth::user()->id;
        $details = $employee_project_mapping->getEmployeeBudget($id);
        $this->layout->content = View::make('employee.budget_details')->with(compact('details'));
    }

    /**
     * @return mixed
     */
    public function renderDetail(){
        $month = $_POST['month'];
        $year = $_POST['year'];
        $employee_id = $_POST['employee_id'];
        $party_plan = new PartyPlan();
        $party_id = $party_plan->getEmployeeParty($month, $year, $employee_id)->id;
        return View::make('employee.view_details')->with(compact('party_id'));

    }

    /**
     * @return mixed
     */
    public function renderView(){
        $party_id = $_POST['party_id'];

        if(isset($_POST['past_project_party_id'])){
             $pastProjectPartId = $_POST['past_project_party_id'];
        }else{
            $pastProjectPartId = "";
        }
       

        return View::make('employee.view_details')->with(compact('party_id', 'pastProjectPartId'));
    }

    /**
     *Renders the list planned_parties view of employee section which display the listing of all the planned parties
     */
    public function plannedParty(){

           $current_user = Auth::User()->id;

         $projectExpenditureDetails = PartyProjectMapping::with('project')
                                ->join('project', 'party_project_mapping.project_id', '=', 'project.id')
                                //->where('party_project_mapping.party_id', '0')
                                ->where('project.project_manager', $current_user)
                                ->orWhere('project.programme_manager', $current_user)
                                ->get();
        $this->layout->content = View::make('employee.planned_parties')->with(compact('projectExpenditureDetails'));
    }

    /**
     * Renders the list of employee view for the past parties
     */
    public function pastParty(){
        $this->layout->content = View::make('employee.past_parties');
    }


}
