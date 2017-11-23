<?php

/**
 * Created by PhpStorm.
 * User: srijanrajput
 * Date: 5/11/2015
 * Time: 11:21 AM
 */
class EmployeeProjectMapping extends Eloquent
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'employee_project_mapping';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    public static $rules = array(
        'name' => 'required|min:5',
        'email' => 'required|email'
    );

    /**
     * Create relation has one with User model with employee_id as local modal's key and id as referenced key
     * @return mixed
     */
    public function employee_rel()
    {
        return $this->hasOne('User', 'id', 'employee_id');
    }

    /**
     * Create relation has one with User model with project_id as local modal's key and id as referenced key
     * @return mixed
     */
    public function project_rel()
    {
        return $this->hasOne('Project', 'id', 'project_id');
    }


//    public function getProjectEmployees($id)
//    {
//        $posts = Post::whereHas('employee_rel', function ($q) {
//            $q->where('content', 'like', 'foo%');
//
//        })->get();
//    }

    /**
     * @param $query
     * @param $start_date
     * @param $end_date
     * @return mixed
     */
    public function scopeEmployeeInRangeGroupEmployeeId($query, $start_date , $end_date )
    {
        $start = explode(',', $start_date);
        $end = explode(',', $end_date);
        // $start='2016-2-19';
        // $end='2017-1-20';
        $startR = $start[1] . '-' . $start[0] .'-'. '01';
        $endR = $end[1] . '-' . $end[0] .'-'. $this->getLastday($end[1], $end[0]);
        return $query->select(array('*', DB::raw('SUM(CASE WHEN  status = "1" THEN 0  ELSE budget END) as budget'), DB::raw('GROUP_CONCAT(MONTH,",",YEAR SEPARATOR ";") as formonths')))
            ->where( DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=',$startR )
            ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $endR)
            ->groupBy('project_id', 'employee_id');
//            ->where('status', '=', 0);
//        return $query->select(array('*', DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE) AS daterange')));
    }

    /**
     * @param $query
     * @param $months
     * @return mixed
     */
    public function scopeEmployeeInRange($query, $months){
        return $query->select(array('*'))
            ->whereIn(DB::raw('CONCAT(`month`,",",`year`)'), $months);
    }

    /**
     * @param $query
     * @param $start_date
     * @param $end_date
     * @return mixed
     */
    public function scopeEmployeeProjectInRange($query, $start_date , $end_date )
    {

        return $query->select(array('*'))
            ->where( DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=',$start_date )
            ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $end_date);

//        return $query->select(array('*', DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE) AS daterange')));
    }

    /**
     * @param $year
     * @param $month
     * @return bool|string
     * returns the last date of the month
     */
    private function getLastday($year, $month)
    {
        $date1 = $year . '-' . $month;
        $d = date_create_from_format('Y-m', $date1);
        $last_day = date_format($d, 't');

        return $last_day;
    }

    /**
     * @param $start_date
     * @param $end_date
     * @param $project
     * @return mixed
     */
    public function getList($start_date, $end_date, $project)
    {
        $start = explode(',', $start_date);
        $end = explode(',', $end_date);
        $startR = $start[1] . '-' . $start[0] .'-'. '01';
        $endR = $end[1] . '-' . $end[0] .'-'. $this->getLastday($end[1], $end[0]);
        return $result = $this ->with(array('employee_rel'=>function($query){
            $query->orderBy('name', 'desc');
        }),
            'project_rel')
            ->whereIn('project_id', $project)
            ->where('hours','>=',40)
            ->where('status','=',0)
            ->employeeInRangeGroupEmployeeId($start_date,$end_date)
            ->get();

    }

    /**
     * @param $party_id
     * @param $status
     */
    public function updateStatus($party_id, $status){
        $employees = PartyDetails::where('party_id','=',$party_id)->lists('employee_id');
        $result = PartyPlan::where('id','=',$party_id)->first();
        $start_date = $result->start_date;
        $end_date = $result->end_date;
        $project_id = PartyProjectMapping::where('project_id', '=', $party_id)->lists('project_id');
        foreach ($employees as $employee) {
            $this->employeeProjectInRange($start_date, $end_date)
                ->where('status', '=', 0)
                ->where('employee_id','=',$employee)
                ->whereIn('project_id', $project_id)
                ->update(array('status'=>$status));
        }
        return;
    }
    /**
     * @param $party_id
     * @param $status
     */
    public function updateStatusMM($party_id, $status){
        $employees = PartyDetails::where('party_id','=',$party_id)->lists('employee_id');
        $result = PartyPlan::where('id','=',$party_id)->first();
        $start_date = $result->start_date;
        $end_date = $result->end_date;
        $project_id = PartyProjectMapping::where('party_id', '=', $party_id)->lists('project_id');
            $this->employeeProjectInRange($start_date, $end_date)
                ->where('status', '=', 1)
                ->whereIn('employee_id', $employees)
                ->whereIn('project_id', $project_id)
                ->update(array('status'=>$status));
        return;
    }

    /**
     * @param $employee_id
     * @return mixed
     */
    public function getEmployeeBudget($employee_id){
        $s = date('Y-m-d');
        $l = date('Y-m-d', strtotime('-9 months'));
       return $this->with('project_rel')->employeeProjectInRange($l, $s)->where('employee_id', '=', $employee_id)->get();
    }

    public function addBudget($party_id){
        $party = PartyPlan::find($party_id);
        $employee_ids = PartyDetails::where('party_id', '=', $party_id)->lists('employee_id');
//        $project_id = PartyDetails::where('party_id', '=', $party_id)->pluck('project_id');

        $start_date = $party->start_date;
        $end_date = $party->end_date;
        $employee_project_months = $this->employeeProjectInRange($start_date, $end_date)->whereIn('project_id', explode(',',$party->project_id))->whereIn('employee_id', $employee_ids)->get();
        $total_months = count($employee_project_months);
        $total_budget = $party->plan_amount - $party->spent_amount;
        $unit_budget = floor($total_budget/$total_months);
        foreach($employee_project_months as $epm){
            $employee_project_mapping = new EmployeeProjectMapping();
            $employee_project_mapping->employee_id = $epm->employee_id ;
            $employee_project_mapping->project_id = $epm->project_id ;
            $employee_project_mapping->month = $epm->month;
            $employee_project_mapping->year = $epm->year;
            $employee_project_mapping->amount_left = $unit_budget;
            $employee_project_mapping->status = 0;
            $employee_project_mapping->save();
        }
    }

    /**
     * returns all employee project mappings for last 6 months and coming 6 months
     * @param $project_id
     */
    public function getMonthlyBudget($project_id = '', $start_date = '', $end_date = ''){

        if($start_date == '') {
            $start_date = date('Y-m-d', strtotime('-6 months'));
        }
        if($end_date == '') {
            $end_date = date('Y-m-d', strtotime('+6 months'));
        }
        if($project_id == '') {
            return $this->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=', $start_date)
                ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $end_date)
                ->groupBy(array('month', 'year'))
                ->get(array(
                    'month',
                    'year',
                    DB::Raw('group_concat(status) as status'),
                    DB::Raw('group_concat(employee_id) as employee_ids'),
//                DB::Raw('group_concat(amount_left) as amount_left'),
                    DB::Raw('SUM(budget) AS allocated'),
                    DB::Raw('SUM(CASE WHEN STATUS =0  THEN budget ELSE 0 END) AS remaining'),
                ));
        }else{
            return $this->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=', $start_date)
                ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $end_date)
                ->where('project_id', '=', $project_id)
                ->groupBy(array('month', 'year'))
                ->get(array(
                    'month',
                    'year',
                    DB::Raw('group_concat(status) as status'),
                    DB::Raw('group_concat(employee_id) as employee_ids'),
//                DB::Raw('group_concat(amount_left) as amount_left'),
                    DB::Raw('SUM(budget) AS allocated'),
                    DB::Raw('SUM(CASE WHEN STATUS =0  THEN budget ELSE 0 END) AS remaining'),
                ));
        }
    }

    /**
     * returns all employee project mappings for last 6 months and coming 6 months
     * @param $project_id
     */
    public function getMonthlyBudgetMonthYear($project_id, $start_date = '', $end_date = '',$month,$year){

        if($start_date == '') {
            $start_date = date('Y-m-d', strtotime('-6 months'));
        }
        if($end_date == '') {
            $end_date = date('Y-m-d', strtotime('+6 months'));
        }

            return $this->where('month', '=', $month)
                ->where('year', '=', $year)
                ->where('project_id', '=', $project_id)
                ->groupBy(array('month', 'year'))
                ->get(array(
                    'month',
                    'year',
                    DB::Raw('group_concat(status) as status'),
                    DB::Raw('group_concat(employee_id) as employee_ids'),
//                DB::Raw('group_concat(amount_left) as amount_left'),
                    DB::Raw('SUM(budget) AS allocated'),
                    DB::Raw('SUM(CASE WHEN STATUS =0  THEN budget ELSE 0 END) AS remaining'),
                ));

    }
    /**
     * @param $query
     * @param string $start_date
     * @param string $end_date
     * @return mixed
     * Calculates month wise allocation of budget between start date and end date
     */
    public function scopeAllocationBudget($query, $start_date = '', $end_date = ''){
        if($start_date == '') {
            $start_date = date('Y-m-d', strtotime('-6 months'));
        }
        if($end_date == '') {
            $end_date = date('Y-m-d', strtotime('+6 months'));
        }
        return $query->where( DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=',$start_date )
            ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $end_date)
            ->groupBy(array('month','year'));

    }

    /**
     * @param int $project_id project id of project table
     * @param string $start_date
     * @param string $end_date
     * @return mixed
     * Calculate the future budget between start_date and end_date for given project
     */
    public function futureBudgetCalculation($project_id, $start_date = '', $end_date = ''){
        $bc = new BaseController();
        if($start_date == '') {
            $start_date = date('Y-m-d', strtotime('-6 months'));
        }

        if(!$bc->isFutureMonth(date('m,Y',strtotime($start_date)))){
            $start_date = date('Y-m-d', strtotime('first day of next month'));
        }
        if($end_date == '') {
            $end_date = date('Y-m-d', strtotime('+6 months'));
        }
        return $this->where( DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '>=',$start_date )
            ->where(DB::raw('CAST(CONCAT(`year`,"-",`month`,"-",1) AS DATE)'), '<=', $end_date)
            ->where('project_id', '=', $project_id)
            ->first(array(
                DB::Raw('SUM(CASE WHEN STATUS =0  THEN budget ELSE 0 END) AS allocated'),
            ));
    }

    /**
     * @param int $party_id id of party plan
     * @return int total future budget
     * Calculate the future budget comsumed in the given party
     */
    public function futureBudgetFromPartyId($party_id){
        $project_ids = PartyProjectMapping::where('party_id', '=', $party_id)->lists('project_id');
        $party = PartyPlan::where('id', '=', $party_id)->first();
        $start_date = $party->start_date;
        $end_date = $party->end_date;
        $total = 0;
        foreach($project_ids as $value){
         $total +=  $this->futureBudgetCalculation($value, $start_date, $end_date)->allocated;
        }
        return $total;
    }


}