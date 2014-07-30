<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


class Cy_base_form_model extends CI_Model
{
	
	protected $field_types = array('Vanilla','Bootstrap');
	
	protected $active_field_type;
	
	protected $loaded 		= FALSE;
	
	protected $fields 		= array();
	
	protected $post_data;
	
	protected $form;
	
	protected $rules;
	
	
	
	// saves the data with the valid method if true
	// if false the developer must call save manually
	protected $auto_save 		= TRUE;
	
	
	protected $error 			= FALSE;
	


	/*
	 * Data creation format
	 * 		
	 * 		[field_type]	=> (optional) (string) field form type definition for /libraries/Form_field.php
	 * 		[fields]		=> (array) of Field format
	 * 
	 */
	 
	/* 
	 * Field format:
	 * 
	 * 		id (unique) (string) (field name and id for the html labels)
	 * 		options (array)
	 * 					[type] 					=> string (field form type, like text, textarea, checkbox...)
	 * 					[rules]					=> string or array
	 * 														string => (rules for codeigniter's form_validation)
	 * 														array => array('insert' => (string), 'update' => (string))
	 * 					[value]					=> (optional) mixed (field data will be set in the form)
	 * 					[additional_parameters] => (optional) additional parameters for the html form
	 * 
	 */
	 
	 /*
	  * (only for file objects)
	  * File field format:
	  * 
	  * 	id (unique) (string) (if fieldName it's not defined, will be used to define file field name defined in the _classData method)
	  * 	options (array)
	  * 			[type]						=> (string) file type must be upload
	  * 			[upload]					=> (boolean) (optional) TRUE|FALSE (true in this case, duh!)
	  * 			[rules]						=> string or array
	  * 														string => (rules for codeigniter's form_validation)
	  * 														array => array('insert' => (string), 'update' => (string))
	  * 			[additional_parameters] 	=> (optional) additional parameters for the html form
	  *
	  */

	
	//messages
	
	protected $messages = array('options_data_error'	=> 'Error in field data options',
								'options_no_type'		=> 'There\'s no field type defined in the options.',
								'invalid_field_data'	=> 'The field options data is invalid.',
								'submit_button_text'	=> 'Submit',
								'table_loaded_empty'	=> 'A table has an unloaded state in a loaded form.',
								'field_already_defined' => 'The field % it\'s already defined');
	

	
	
	function __construct($options = NULL)
	{
		
		$this->load->library('cy_form_generator/Form_field');
		
		if(empty($options))
		{
			return FALSE;
		}
		else
		{
			$this->set_options($options);
		}
	}


	 function form_definition($options = array())
	 {
	 	// all the data defined as in the previous comments
	 	
	 	$local_options = array();
		
		$options = array_merge($options, $local_options);
		
		$this->set_options($options);
	 }

	
	function set_options($options)
	{
		if(array_key_exists('field_type', $options))
		{
			$this->set_field_type($options['field_type']);
		}
		
		
		if(array_key_exists('fields', $options))
		{
			foreach($options['fields'] as $key => $field_data)
			{
				$this->set($key, $field_data);
			}
		}
	}


	function set_field_type($type)
	{
		if(in_array($type, $this->field_types))
		{
			$this->active_field_type = $type;
		}
	}

	function get_field_type()
	{
		if(is_null($this->active_field_type))
		{
			return reset($this->field_types);
		}
		
		return $this->active_field_type;
	}

	/**
	 * ====================================================================
	 * CRUD functions
	 */

	
	function save($data = NULL)
	{
		if($data === NULL)
		{
			$data = $this->post_data;
		}
		
		$data = $this->sanitize_data($data);
		
		return $data;
		
	}


	/**
	 * ====================================================================
	 * Form flow functions
	 */

	 
	/**
	 * checks if the form post data is valid and if so, it saves automatically the data
	 *
	 * @return null
	 * @author Patroklo
	 */
	 function valid($auto_save = NULL)
	 {
	 	if($auto_save !== NULL && is_bool($auto_save))
		{
			$this->auto_save = $auto_save;
		}
		

		//add rules of the form validation
		$this->add_rules();
		
		$this->activate_form_rules();
		
		if($this->check_errors() == FALSE)
		{
			return FALSE;
		}
		else
		{
			$this->post_data = $this->input->post();
			
			if($this->auto_save === TRUE)
			{
				$this->save();
			}
			
			return TRUE;
		}
	 }
	 
	 protected function check_errors($fields = NULL)
	 {
	 	if($fields === NULL)
		{
			$fields = $this->fields;
		}
		else
		{
			if(!is_array($fields))
			{
				$fields = array($fields);
			}
			
			$fields =  array_intersect_key($this->fields, array_flip($fields));
		}
		
		if($this->form_validation->run() === FALSE)
		{

			$this->error = validation_errors();
			
			foreach($fields as $field)
			{
				$field->set_error(form_error($field->get_id()));
			}
			
			return FALSE;
			
		}

		return TRUE;

	 }
	 

	/**
	 * ====================================================================
	 * Data functions
	 */
	 
	/**
	 * sets the object as loaded or not loaded form, this means that when saving, the form data will updated 
	 * or inserted depending of this value
	 * 
	 * @return boolean
	 * @author  Patroklo
	 */
	function set_loaded($loaded)
	{
		if(!is_bool($loaded))
		{
			return  FALSE;
		}	

		$this->loaded = $loaded;
		
		return TRUE;
	}

	function is_loaded()
	{
		return $this->loaded;
	}
	
	/**
	 * Sanitizes the data passed through parameter
	 *
	 * @return array mixed
	 * @author  Patroklo
	 */
	protected function sanitize_data($data)
	{
		// TODO añadir opciones anti XSS y demás mierdas
	
		foreach($data as $key => $d)
		{
			if(!array_key_exists($key, $this->fields))
			{
				unset($data[$key]);
			}
		}
	 	
		return $data;
	}
	 

	/**
	 * ====================================================================
	 * Field functions
	 */

	 /**
	  * adds rules into the $this->rules variable
	  * 
	  * this array will be later used in the activate_form_rules method
	  * that will send this rules into the form_validation library
	  *
	  * @return void
	  * @author  Patroklo
	  */
	 
	 function add_rules($rules = NULL)
	 {
	 	$config = array();
		
		if(empty($this->rules))
		{
		 	foreach($this->fields as $key => $field)
			{
				$this->add_rule($key);
			}
		}
		
		if(!empty($rules) && is_array($rules))
		{
			foreach($rules as $key => $rule)
			{
				$this->rules[$key] = $config[$key] + $rule;
			}
		}

	 }
	 
	 /**
	  * adds rule for one field
	  *
	  * @return void
	  * @author  Patroklo
	  */
	  
	 function add_rule($field_id)
	 {
	 	$field = $this->fields[$field_id];
		
	 	$this->rules[$field->get_id()] = array('field' => $field->get_name(), 'label' => $field->get_name(), 'rules' => $field->get_rules($this->is_loaded()));
	 }
	 
	 
	 /**
	  * sends the rules from $this->rules variable into the form_validation
	  *
	  * @return void
	  * @author  
	  */
	 function activate_form_rules() 
	 {
	 	$this->load->library('Form_validation');
		
		if(!empty($this->rules))
		{
			$this->form_validation->set_rules($this->rules); 
		}
	 }
	 
	
	
	/**
	 * adds or edits the data of a form field
	 *
	 * @return null
	 * @author Patroklo
	 */
	function set($field_id, $options = NULL) 
	{

		if(is_array($field_id) and array_key_exists('id', $field_id) and $options === NULL)
		{
			$options = $field_id;
			$field_id = $field_id['id'];
		}
		elseif(!is_array($field_id) and $options === NULL)
		{
			$this->exception($this->get_message('options_data_error'));
		}
		
		if(!is_array($options))
		{
			$this->exception($this->get_message('options_data_error'));
		}
		
		$options['id'] = $field_id;
		
		if(!array_key_exists($field_id, $this->fields))
		{
			if(!array_key_exists('type', $options))
			{
				$this->exception($this->get_message('invalid_field_data'));
			}
			
			$options['type'] = $options['type'].'_'.$this->get_field_type();
			
			$this->fields[$field_id] = Field_factory::create($options);
			
			if($this->fields[$field_id] === FALSE)
			{
				$this->exception($this->get_message('options_no_type'));
			}
		}
		else
		{
			$this->fields[$field_id]->set_options($options);
		}
	}
	
	/**
	 * removes the field from the field list
	 *
	 * @return NULL
	 * @author  Patroklo
	 */
	function remove($field_id) 
	{
		if(array_key_exists($field_id, $this->fields))
		{
			unset($this->fields[$field_id]);
		}
	}


	/**
	 * return the error messages from the form validation
	 *
	 * @return FALSE / array with text
	 * @author  Patroklo
	 */
	function get_errors($fields = NULL) 
	{
	 	if($fields === NULL)
		{
			$fields = $this->fields;
		}
		else
		{
			if(!is_array($fields))
			{
				$fields = array($fields);
			}
			
			$fields =  array_intersect_key($this->fields, array_flip($fields));
		}
		
		if($this->error == FALSE)
		{
			return FALSE;
		}
		
		$return_data = array('global_error' => $this->error);
		
		$field_errors = array();
		
		foreach($fields as $field)
		{
			$field_errors[$field->get_id()] = $field->get_error();
		}
		
		if(!empty($field_errors))
		{
			$return_data['field_errors'] = $field_errors;
		}
		else 
		{
		 	$return_data['field_errors'] = FALSE;
		}
		
		return $return_data;
	}
	
	function get_form($options = array())
	{
		if(is_null($this->form))
		{
			$this->form = Field_factory::form($this->get_field_type(), $options);
		}
		
		return $this->form;
	}
	

	/**
	 * maps the entire defined form
	 *
	 * @return HTML
	 * @author  Patroklo
	 */
	function map() 
	{
		$return_html = '';
		
		$form = $this->get_form(array('submit_text' => $this->get_message('submit_button_text')));
		
		$return_html.= $form->start_form();
		
		$errors = $this->get_errors();
		

		if(is_array($errors))
		{
			$return_html.= $form->show_errors($errors['field_errors']);
		}
		
		foreach($this->fields as $key => $field)
		{
			$return_html.= $this->show_field($key);
		}
		
		$return_html.= $form->submit_button();
		
		$return_html.= $form->end_form();
		
		return $return_html;
	}
	
	
	function show_field($field_name)
	{
		$field = $this->fields[$field_name];
		
		$field->set_value(set_value($field_name, $field->get_value()));
		
		return $field->show();
		
	}
	
	protected function get_message($id, $additional_string = NULL)
	{
		if(!is_null($additional_string))
		{
			return str_replace('%', $additional_string, $this->messages[$id]);
		}
		else
		{
			return $this->messages[$id];
		}
	}
	
	protected function exception($message)
	{
		throw new Exception($message);
	}

	
}