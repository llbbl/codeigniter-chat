<?php

class Test extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('unit_test');
        $this->load->model('Chatmodel');
    }

    public function index() {
        // Set test mode to display only necessary information
        $this->unit->set_test_items(array('test_name', 'result'));

        // Test 1: Test getMsg method
        $result = $this->Chatmodel->getMsg();
        $this->unit->run(is_object($result), TRUE, 'Chatmodel->getMsg() returns an object');
        
        // Test 2: Test getMsg with limit parameter
        $result = $this->Chatmodel->getMsg(5);
        $this->unit->run(is_object($result), TRUE, 'Chatmodel->getMsg(5) returns an object');
        
        // Test 3: Test that getMsg returns a database result object
        $result = $this->Chatmodel->getMsg();
        $this->unit->run(method_exists($result, 'result'), TRUE, 'Chatmodel->getMsg() returns a database result object');

        // Output the test results
        echo $this->unit->report();
    }
}