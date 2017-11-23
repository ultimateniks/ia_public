<?php

class HomeController extends BaseController
{

    /*
    |--------------------------------------------------------------------------
    | Default Home Controller
    |--------------------------------------------------------------------------
    |
    | You may wish to use controllers instead of, or in addition to, Closure
    | based routes. That's great! Here is an example controller method to
    | get you started. To route to this controller, just add the route:
    |
    |	Route::get('/', 'HomeController@showWelcome');
    |
    */

    public function showWelcome()
    {
        return View::make('hello');
    }

    /**
     * @return mixed
     * Checks if the user is logged in
     * if Auth::check returns true then user is redirected to intended url by default according to the roles
     * Otherwise if Auth::check return false then user is User if redirected to login again
     */
    public function login()
    {
        $message_error = "";
        if (Auth::check()) {
            if (Auth::user()->role == 'admin') {
                return Redirect::intended('admin/requests/reimburse');
            } elseif (Auth::user()->role == 'mm') {
                return Redirect::intended('programmemanager/home');
            } elseif (Auth::user()->role == 'project_manager') {
                return Redirect::intended('projectmanager/home');
            } elseif (Auth::user()->role == 'coordinator') {
                return Redirect::intended('coordinator/home');
            } elseif (Auth::user()->role == 'employee') {
                return Redirect::intended('employee/planned');
            }
        } else {
            if (Auth::attempt(array())) {
                return Redirect::to('login');
                // Redirect to link after login
            }
            // Redirect to un-logged in page
        }
    }

    /**
     * Logout the user from application as well as Central Authentication Server
     */
    public function logout()
    {
        Auth::logout();
        Session::flush(); // Destroy all sessions
        if (session_status() === PHP_SESSION_ACTIVE) //Checks if session is start and has some value
        {
            session_destroy();
        } // To remove the extra session variables
        header('Location: https://cas.nagarro.com/logout');
        exit;
    }

    public function noUser()
    {
        $this->layout = View::make('home.master');
        $this->layout->content = View::make('home.no_user');
    }

    public function test()
    {
     echo \Helpers\Helper::getFormatedDate('2015-07-30');exit;
    }
}
