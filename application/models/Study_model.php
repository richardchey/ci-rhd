<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Study_model extends MY_Model
{
	protected $group_name = 'study_master';

	public function __construct($param = array())
	{
		parent::__construct();
	}

	public function fetchUserList()
	{
		$a = $this->db->from('user')->get()->row_array();
		print_r($a);
	}
}
