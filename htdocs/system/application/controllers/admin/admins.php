<?php
/**
 * Auth Controller Class
 *
 * Security controller that provides functionality to handle logins, logout and registration
 * requests.  It also can verify the logged in status of a user and his permissions.
 *
 * The class requires the use of the DB_Session, FAL_validation and FreakAuth libraries.
 *
 * @package     FreakAuth
 * @subpackage  Controllers
 * @category    Authentication
 * @author      Daniel Vecchiato (danfreak) & Christophe Gragnic (grahack)
 * @copyright   Copyright (c) 2007, 4webby.com
 * @license		http://www.gnu.org/licenses/lgpl.html
 * @link 		http://4webby.com/freakauth
 * @version 	1.1
 *
 */

class Admins extends Controller
{	
	/**
	 * Initialises the controller
	 *
	 * @return Admin
	 */
    function Admins()
    {
        parent::Controller();
        
        ////////////////////////////
		//CHECKING FOR PERMISSIONS
		///////////////////////////
		//-------------------------------------------------
        //only SuperAdmin can manage admins
        //if you are not a superAdmin go away
        
        $this->freakauth_light->check('superadmin', true);
        
        //-------------------------------------------------
        //END CHECKING FOR PERMISSION
        
        $this->_container = $this->config->item('FAL_template_dir').'template_admin/container';
        
        //loads necessary libraries and models
        $this->lang->load('freakauth');
        $this->load->model('FreakAuth_light/usermodel', 'usermodel');
        if ($this->config->item('FAL_use_country'))
            $this->load->model('country', 'country_model');
        //lets load the validation class if it hasn't been already loaded
        //it is needed by the FAL_validation library
        if ( ! class_exists('CI_Validation'))
		{
		     $this->load->library('validation');
		}
        $this->load->library('FAL_validation', 'fal_validation');
		$this->fal_validation->set_error_delimiters($this->config->item('FAL_error_delimiter_open'), $this->config->item('FAL_error_delimiter_close'));

		//sets the necessary form fields
		$fields['user_name'] = $this->lang->line('FAL_user_name_label');
        $fields['password'] = $this->lang->line('FAL_user_password_label');
        $fields['password_confirm'] = $this->lang->line('FAL_user_password_confirm_label');
        $fields['email'] = $this->lang->line('FAL_user_email_label');
        $fields['role'] = 'role';
        $fields['banned'] = 'banned';
        
        //if activated in config, sets the select country box
        if ($this->config->item('FAL_use_country'))
        {
            $fields['country_id'] = $this->lang->line('FAL_user_country_label');
        }
        
        //additionalFields($fields);
        
        $this->fal_validation->set_fields($fields);
    	
    }
	
    	// --------------------------------------------------------------------
	
    /**
     * Displays the admins list.
     *
     */
    function index()
    {
		//let's paginate results
		$this->load->library('pagination');
		
		$config['base_url'] = base_url().$this->config->item('index_page').'/'.'admin/admins';
		$config['uri_segment'] = 3;
		$config['per_page'] = $this->config->item('FAL_admin_console_records_per_page');
		$config['full_tag_open'] = '<p>';
		$config['full_tag_close'] = '</p>';
		$config['cur_tag_open'] = '<b>';
		$config['cur_tag_close'] = '</b>';
		$config['next_link'] = '&gt';
		$config['prev_link'] = '&lt';
		
		$fields='id';
		$query = $this->usermodel->getAdmins($fields);
		
		$config['total_rows'] = $query->num_rows();
		$this->pagination->initialize($config);
		$query->free_result();
			
		$page = $this->uri->segment(3, 0);
    	
    	$fields= 'id, user_name, role';
    	
    	$limit= array('start'=>$config['per_page'],
    				  'end'=>$page
    					);
		
    	$query = $this->usermodel->getAdmins($fields, $limit);

		
		if ($query->num_rows()>0)
		{
			$i=1;
			foreach ($query->result() as $row)
			{
                // when do we display links for editing or deleting a user ?
                // note: if we are in the admins controller, we are a superadmin
                // we display the edit link if
                // - the user in the table is not a superadmin
                // - we are a superadmin editing himself
                $data['user'][$i]['show_edit_link'] =
                    ($row->role != 'superadmin' OR $row->id == getUserProperty('id'));
                // we display the delete link if
                // - the user in the table is not a superadmin
                $data['user'][$i]['show_delete_link'] =
                    ($row->role != 'superadmin');
                
                // then we just fill the infos
				$data['user'][$i]['id']= $row->id;
				$data['user'][$i]['user_name']= $row->user_name;
				$data['user'][$i]['role']= $row->role;
				$i++;
			}
			
			$query->free_result();
		}
		else 
		{
			// If we arrive here, it means that we have no admins in the db
			// hence no SUPERADMIN. But only superadmins are allowed to
			// use this controller.
			// The only way to arrive here is to log in as a superadmin,
			// then delete all superadmins and admins 'by hand'
			// (since FAL do not allow it), and try to display the admins list.
			show_error('No superadmin or admin in the database. Please reinstall FreakAuth light.');
		}
			
		//let's display the page
		$data['heading'] = 'VIEW admins';
		$data['action'] = 'Manage admins';
		$data['pagination_links'] = $this->pagination->create_links();
		$data['controller'] = 'admins';
		//$data['page'] = $this->config->item('FAL_template_dir').'template_admin/users/list';
						
		$this->load->vars($data);
		
		$this->load->view('admin/cashier/list');
		//$this->output->enable_profiler(TRUE);
    }


    // --------------------------------------------------------------------
    
	/**
	 * View record details
	 *
	 * @param record id $id
	 */
    function show($id)
    {	    			
    	$query = $this->usermodel->getUserById($id);
		
		if ($query->num_rows() == 1)
        {
            $row = $query->row();
            
            // initializing two flags, for the edit and delete links
            // we can edit the displayed admin if
            //  - we are a superadmin
            //  - OR the displayed user is not a superadmin
            $data['can_edit_user'] = (
                getUserProperty('role') != 'superadmin'
                OR $row->id == getUserProperty('id')
                OR $row->role != 'superadmin'
                );
            // we cannot delete a superadmin
            $data['can_delete_user'] =
                ($row->role != 'superadmin');
            
			$data['user']['id']= $row->id;
			$data['user']['user_name']= $row->user_name;
			$data['user']['email']= $row->email;
			$data['user']['role']= $row->role;
			$data['user']['banned']= $row->banned;
			
			//$countries = null;            
		    if ($this->config->item('FAL_use_country') && strlen($row->country_id))
		    {
		    	$query = $this->country_model->getCountryById($row->country_id);
                if (isset($query))
                {
                    $row = $query->row();
                        
                    //SELECT name FROM country WHERE id= $data['user']['country_id']
                    $data['user']['country'] = $row->name;
                }
		    }
		    
		    if (isset($query)) $query->free_result();
		    
		    if ($this->config->item('FAL_create_user_profile')==TRUE)
		    {
		    	$data['user_profile']= $this->freakauth_light->_getUserProfile($id);
		    	$data['f_r'] = $this->freakauth_light->_buildUserProfileFieldsRules();
		    	$data['label'] = $data['f_r']['fields'];
		    }
		    
		    
		    
        }
        else 
        {
        	$data['error_message']='The record you are looking for does not exist';
        }
    	
	    	$data['heading'] = 'Manage admin';
	    	$data['action'] = 'View admin';
	    	$data['controller'] = 'admins';
	    	//$data['page'] = $this->config->item('FAL_template_dir').'template_admin/users/detail';

	        $this->load->vars($data);
	        
	    	$this->load->view('admin/cashier/detail');
	    	
	    	// for debugging
	    	// $this->output->enable_profiler(TRUE);
	    	
    }
    
    // --------------------------------------------------------------------
    
    /**
     * Handles the post from the add admin form.
     *
     */
    
    function add()
    {      
    	//set validation rules
    	$rules['user_name'] = 'trim|required|xss_clean|username_check|username_backend_duplicate_check';
        $rules['password'] = 'trim|required|xss_clean|password_backend_check';
        $rules['password_confirm'] = "trim|required|xss_clean|matches[password]";
        $rules['email'] = 'trim|required|valid_email|xss_clean|email_backend_duplicate_check';
        $rules['role'] = 'required';
        $rules['banned'] = 'is_numeric';
        
        $this->fal_validation->set_message('is_numeric', 'must be numeric');
        //do we want to set the country?
        //(looks what we set in the freakauth_light.php config)
        if ($this->config->item('FAL_use_country'))
        {
            $rules['country_id'] = $this->config->item('FAL_user_country_field_validation_register');
        }
        
        //getting user profile custom data
	    if ($this->config->item('FAL_create_user_profile')==TRUE)
		{	
		    $data = $this->freakauth_light->_buildUserProfileFieldsRules();
		    $rules_profile = $data['rules'];
		    $fields = $data['fields']; 
		    
		    $this->fal_validation->set_rules($rules_profile);
		    	
		    $this->fal_validation->set_fields($fields);
		}
		        
        $this->fal_validation->set_rules($rules);
       
    	
    	
    	//if validation unsuccesfull & data not ok
        if ($this->fal_validation->run() == FALSE)
		{
			//$countries = null;            
	        if ($this->config->item('FAL_use_country'))
	        {
	    		//SELECT * FROM country
	            $data['countries'] = $this->country_model->getCountriesForSelect();
	        }

			$data['heading'] = 'Admins management';
	    	$data['action'] = 'Add admin';
	    	$data['role_options'] = array_keys($this->config->item('FAL_roles'));
	    	//$data ['page'] = $this->config->item('FAL_template_dir').'template_admin/users/add';
            				
	        $this->load->vars($data);
	        
	    	$this->load->view('admin/cashier/add');
	    	//$this->output->enable_profiler(TRUE);
		}
		//if everything ok
		else
		{	 
			$values=$this->_get_form_values();
			
        	//insert data in DB
        	$this->usermodel->insertUser($values['user']);
        	
        	
        	//if we want the user profile as well
	        if($this->config->item('FAL_create_user_profile'))
	        {	
	              //let's get the last insert id
	              $values['user_profile']['id']= $this->db->insert_id();
	              $this->load->model('Userprofile');
	              $this->Userprofile->insertUserProfile($values['user_profile']);
	        }
			//set a flash message
			$msg = $this->db->affected_rows().$this->lang->line('FAL_user_added');
			flashMsg($msg);
			
			//redirect to list
			redirect('admin/admins', 'location');
		}
        	
    }
    

    // --------------------------------------------------------------------
    
    /**
     * Manages the edit
     * superadmins can only be edited by themselves
     *
     * @access public
     * @param integer $id the id of the admin
     */
    function edit($id)
    {
        // we only allow edition of this admin if he/she is
        // - not a superadmin OR
        // - a superadmin editing himself
        $allowed = (
            (getUserPropertyFromId($id, 'role') != 'superadmin')
            OR (getUserProperty('id') == $id)
        );
        if (!$allowed) $this->freakauth_light->denyAccess(getUserProperty('role'));
    	
    	//set validation rules
    	$rules['user_name'] = 'trim|required|xss_clean|username_check|username_backend_duplicate_check';
        $rules['password'] = 'trim|xss_clean|password_backend_check';
        $rules['password_confirm'] = "trim|xss_clean|matches[password]";
        $rules['email'] = 'trim|required|valid_email|xss_clean|email_backend_duplicate_check';
        $rules['role'] = 'required';
        $rules['banned'] = 'is_numeric';
        
        
        $this->fal_validation->set_message('is_numeric', 'must be numeric');
        //do we want to set the country?
        //(looks what we set in the freakauth_light.php config)
        if ($this->config->item('FAL_use_country'))
        {
            $rules['country_id'] = $this->config->item('FAL_user_country_field_validation_register');
        }
            
        //getting user profile custom data
	    if ($this->config->item('FAL_create_user_profile')==TRUE)
		{	
		    $data = $this->freakauth_light->_buildUserProfileFieldsRules();
		    $rules_profile= $data['rules'];
		    $fields = $data['fields']; 
		    
		    $this->fal_validation->set_rules($rules_profile);
		    
		}
        
        $this->fal_validation->set_message('is_numeric', 'must be numeric');
        
        $this->fal_validation->set_rules($rules);
        
        //id field needed for validation
        $fields['id'] = 'id';
        $fields['role'] = 'role';
        
        $this->fal_validation->set_fields($fields);
        
		$data['role_options'] = array_keys($this->config->item('FAL_roles'));
    	//this avoid 1 extra query if validation doesn't return true
        if ($id!='')
        {	
        	//gets values for the edit form
        	$query = $this->usermodel->getUserById($id);
        
		
	       	foreach ($query->result() as $row)
		        	{
		        		$data['user']['id']= $row->id;
		        		$data['user']['user_name']= $row->user_name;
		        		$data['user']['email']= $row->email;
		        		$data['user']['country_id']= $row->country_id;
		        		$data['user']['role']= $row->role;
		        		$data['user']['banned']= $row->banned;
		        	}
		        	
		    $query->free_result();
		    
		    

		    if ($this->config->item('FAL_create_user_profile')==TRUE)
			{
				$data['user_prof']= $this->freakauth_light->_getUserProfile($id);
		    	$data['f_r'] = $this->freakauth_light->_buildUserProfileFieldsRules();
		    	$data['fields'] = $data['f_r']['fields']; 
			}
		    
	    }

	    //$countries = null;            
	    if ($this->config->item('FAL_use_country'))
	    {
	    	//SELECT * FROM country
	        $data['countries'] = $this->country_model->getCountriesForSelect();
	    }
	    	  	
		if ($this->fal_validation->run() == FALSE)
        {
               	$data['heading'] = 'Admin management';
	        	$data['action'] = 'Edit admin';
	        	$data['controller'] = 'admins';
	        	//$data ['page'] = $this->config->item('FAL_template_dir').'template_admin/users/edit';

	        	$this->load->vars($data);

	        	$this->load->view('admin/cashier/edit');
	        	
	        	//$this->output->enable_profiler(TRUE);

        }
    	
		//if everything ok
		else
		{			
			//get form values
			$values=$this->_get_form_values();
			
			$id = $values['user']['id'];
			
			//update data in DB
			$where=array('id' => $id);
        	$this->usermodel->updateUser($where, $values['user']);
        	//rows changed in user table
        	$user_tb_affected = $this->db->affected_rows();
        	
        	//if we want the user profile as well
	        if($this->config->item('FAL_create_user_profile'))
	        {	
	              //let's get the last insert id
	              $this->load->model('Userprofile');
	              $this->Userprofile->updateUserProfile($id, $values['user_profile']);
	              //rows changed in user_profile table
	              $userprofile_tb_affected = $this->db->affected_rows();
	        }
        	
			//lets get the number of rows changed
        	$affected_rows = max($user_tb_affected, $userprofile_tb_affected);
			//set a flash message
			$msg = $affected_rows.$this->lang->line('FAL_user_edited');
        	flashMsg($msg);
			
			//redirect to list
			redirect('admin/admins', 'location');
		}
        
    }
    
        // --------------------------------------------------------------------
    
    /**
     * Deletes an administrator, but not a superadmin
     *
     * @access public
     * @param integer $id the id of the admin
     */
    function del($id)
    {
        // we only allow edition of this admin if if he/she is
        // - not a superadmin
        $allowed = (getUserPropertyFromId($id, 'role') != 'superadmin');
            
        if (!$allowed) $this->freakauth_light->denyAccess(getUserProperty('role'));
        
        $this->usermodel->deleteUser($id);
        //set a flash message
        $msg = $this->db->affected_rows().$this->lang->line('FAL_user_deleted');
        
        if ($this->config->item('FAL_create_user_profile')==TRUE)
        {
            $this->load->model('Userprofile');
            $this->Userprofile->deleteUserProfile($id);
        }
        $this->usermodel->deleteUser($id) ;
                
        flashMsg($msg);
        redirect('admin/admins', 'location');
    }
    
    // -------------------------------------------------------------------- 
    
    /**
     * Checks if form $_POST data are set and valid
     * assigns the $_POST data to an array and returns it
     *
     * @return array of form values
     */
    function _get_form_values()
    {
        if (isset($_POST['id'])) 
        {
        	//for edit record
        	$values['user']['id']=$_POST['id']; 
        }

        $values['user']['user_name'] = $this->input->post('user_name', TRUE);
        $values['user']['password'] = $this->input->post('password');
        $values['user']['email'] = $this->input->post('email');
        $values['user']['country_id'] = $this->input->post('country_id');
		$values['user']['banned'] = $this->input->post('banned');
		$values['user']['role'] = $this->input->post('role');
		
		//let's get the custom user profile  values
		if ($this->config->item('FAL_create_user_profile')==TRUE)
		{	
		    $this->load->model('Userprofile', 'userprofile');
		    
		    //array of fields
  			$db_fields=$this->userprofile->getTableFields();

  			//number of DB fields -1
  			//I put a -1 because I must subtract the 'id' field
  			$num_db_fields=count($db_fields) - 1;
  		
  			//I use 'for' instead of 'foreach' because I have to escape the 'id' field that has key=0 in my array
	  		for ($i=1; $i<=$num_db_fields;  $i++)
			{
				$values['user_profile'][$db_fields[$i]]=$this->input->post($db_fields[$i]);
			}
		 }
		
        //let's treat our banned yes/no checkbox
        if (isset($_POST['banned']) AND $_POST['banned'] =='') 
        {
        	//let's assign value zero (not banned)
        	$values['user']['banned']=0; 
        }

        if (($values['user']['user_name'] != false) && ($values['user']['email'] != false))
        {
            //necessary if password is not reset in edit()
        	if ($values['user']['password'] !='')  
            {
	        	$password = $values['user']['password'];
	        	//encrypts the password (md5)
	        	$values['user']['password'] = $this->freakauth_light->_encode($password);
            }
            else 
            {
            	unset($values['user']['password']);
            }

        	return $values;
        }
        
        return false;
    }
 
}
?>
