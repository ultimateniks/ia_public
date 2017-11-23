<?php

class BaseController extends Controller
{


    /**
     * Setup the layout used by the controller.
     *
     * @return void
     */
    protected $layout = 'layouts.master';

    /**
     * Setup the layout used by the controller.
     *
     * @return void
     */
    protected function setupLayout()
    {
        if (!is_null($this->layout)) {
            $this->layout = View::make($this->layout);
        }
    }

    /**
     * @param int $party_id
     * @return mixed the total amount as per allocation
     * Calculates the total amount as per allocation of the given party id
     */
    public static function getTotalBudget($party_id)
    {
        return DB::table('party_project_mapping')
            ->where('party_id', '=', $party_id)
            ->sum('budget_amount');
    }

    /**
     * @param int $party_id
     * @return string the comma separeted list of names of project for the given party id
     */
    public static function getProjectFromPartyId($party_id)
    {
        $project_array = DB::table('party_project_mapping')
            ->select( DB::raw('CONCAT(client.name, "/", project.name) AS project_client'))
            ->join('project', 'party_project_mapping.project_id', '=', 'project.id')
            ->join('client', 'client.id', '=', 'project.client')
            ->where('party_project_mapping.party_id', '=', $party_id)
            ->lists('project_client');
        $project_string = implode(", ",$project_array);
        return $project_string;
    }

    /**
     * @param int $project_id
     * @return mixed project name
     */
    public function getProjectName($project_id){
        return Project::where('id', '=', $project_id)->pluck('name');
    }

    /**
     * @param int $project_id
     * @return mixed project_client in the format client_name/project_name
     */
    public static function getProjectClient($project_id){
    return DB::table('project')
        ->select( DB::raw('CONCAT(client.name, "/", project.name) AS project_client'))
        ->join('client', 'client.id', '=', 'project.client')
        ->where('project.id', '=', $project_id)
        ->pluck('project_client');
    }

    /**
     * @param int $id employee id
     * @return mixed employee name
     */
    public static function getEmployeeNameFromId($id){
        return User::where('id', '=', $id)->pluck('name');
    }

    /**
     * @param string $date in the php standard date format
     * @return bool
     * returns true the the date is greater than the last day of current month and false otherwise
     */
    public function isFutureMonth($date){
       $current_month = date('m');
        $current_year = date('Y');
        $compare_month = date('m',strtotime($date));
        $compare_year = date('Y',strtotime($date));

        if($compare_year > $current_year){
            return true;
        }elseif($compare_year == $current_year){
            if($compare_month > $current_month){
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $date1 in the php standard format
     * @param string $date2 in the php standard format
     * @return bool|int|string
     * Calculates the no. of future months
     * if the $date1 is greater than the last day of current month then the difference of months is calculated
     * else the difference if calculated from the first day of the next month relative to currnet month
     */
    public static function getNoOfMonths($date1, $date2){
        $bc = new BaseController();
        if(!$bc->isFutureMonth($date1) ){
            $date1 = date('Y-m-d', strtotime('first day of next month'));
        }
        $ts1 = strtotime($date1);
        $ts2 = strtotime($date2);

        $year1 = date('Y', $ts1);
        $year2 = date('Y', $ts2);

        $month1 = date('m', $ts1);
        $month2 = date('m', $ts2);

        $diff = (($year2 - $year1) * 12) + ($month2 - $month1+1);
        if($diff>0)
        return $diff;
        else
            return 0;
    }

    /**
     * @param int $id employee id
     * @return mixed employee name
     */
    public static function getEmployeeNameEmailFromId($id)
    {
        $name = User::where('id', '=', $id)->pluck('name');
        $email = User::where('id', '=', $id)->pluck('email');
        return $name . ' (' . $email . ')';
    }

    public static function getClientProject($projectId){
        $project = Project::find($projectId);
        $client = $project->clientName->name;

        return $client."/".$project->name;
    }
    
     public static function getProjectPartyDetail($party_id)
    {
        return DB::table('party_project_mapping')
            ->where('party_id', '=', $party_id)
            ->get();
    }

}
