<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        // If user is already logged in, redirect to chat
        if (session()->get('logged_in')) {
            return redirect()->to('/chat');
        }

        // Otherwise, redirect to login page
        return redirect()->to('/auth/login');
    }
}
