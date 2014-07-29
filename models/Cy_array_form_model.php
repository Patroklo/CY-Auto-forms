<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');


require_once APPPATH.'modules/cy_form_generator/models/Cy_base_form_model.php';

class Cy_array_form_model extends Cy_base_form_model
{
	
	
	function __construct($options = NULL)
	{
		parent::__construct($options);
		
		$this->form_definition();
	}
	
	
	
	
	// Database type => Correcaminos
	
	/*
	 * Data creation format
	 * 		
	 * 		[field_type]	=> (optional) (string) field form type definition for /libraries/Form_field.php
	 * 		[objects] 		=> (string) (loaded or not (setted as insert or update))
	 * 						   (optional) (array)
	 * 										[name]	=> (string) the object class name
	 * 										[alias] => (string) neccesary when using two or more objects of the same class name
	 * 															in order to be able to tell which is which
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
	 * 					[object_type]			=> (optional) string (object of the field, if not setted, then won't be used in database related methods)
	 * 														  (it's the name or alias (in case there is an alias defined) of the object)
	 * 					[fieldName]				=> (optional) string (field name in the table) if not set, will try to use the id as fieldName
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
	  * 			[object_type]				=> (string) object that holds the file reference
	  * 													(it's the name or alias (in case there is an alias defined) of the object
	  * 			[fieldName]					=> (optional) (string) field name defined in the _classData method
	  * 			[additional_parameters] 	=> (optional) additional parameters for the html form
	  *
	  */
	 
	 function form_definition($options = NULL)
	 {
	 	// all the data defined as in the previous comments
		
		$this->set_options($options);
	 }
	 
	 protected $objects;
	 
	/**
	 * sets the form options and fields for the model
	 *
	 * @return void
	 * @author  Patroklo
	 */

	function set_options($options)
	{
		if(array_key_exists('field_type', $options))
		{
			$this->set_field_type($options['field_type']);
		}
		
		// we set the object names in the array
		// objects won't be setted until the method carga it's called or the $_post it's read
		// then the object will be made empty
		
		if(array_key_exists('objects', $options))
		{
			if(!is_array($options['objects']))
			{
				$options['objects'] = array($options['objects']);
			}
			
			foreach($options['objects'] as $object)
			{
				$this->create_object($object);
			}
		}
		
		
		if(array_key_exists('fields', $options))
		{
			foreach($options['fields'] as &$field_data)
			{
				if(!array_key_exists('fieldName', $field_data['options']))
				{
					$field_data['options']['fieldName'] = $field_data['id'];
				}
				
				$field_data['options']['name'] = $field_data['id'];
				
				if(array_key_exists('upload', $field_data['options']) && $field_data['options']['upload'] == TRUE)
				{
					$this->set_upload($field_data);
				}
				
				$this->set($field_data['id'], $field_data['options']);
			}
			
			reset($this->fields);
		}
	}

	/**
	 * creates an object in the $this->objects variable
	 *
	 * @return void
	 * @author  Patroklo
	 */

	private function create_object($object_data)
	{
			if(is_array($object_data))
			{
				$object_name = $object_data['name'];
				$object_alias= $object_data['alias'];
			}
			else
			{
				$object_name = $object_data;
				$object_alias= $object_data;
			}
			
			$this->objects[$object_alias] = array('name'	=> $object_name,
												  'data'	=> NULL);

			if(is_array($object_data) && array_key_exists('upload_parent', $object_data))
			{
				$this->objects[$object_alias]['upload_parent'] = $object_data['upload_parent'];
			}
			else 
			{
				$this->objects[$object_alias]['upload_parent'] = NULL;
			}
	}


	/**
	 * checks if the object passed by parameters it's a file upload object
	 *
	 * @return BOOLEAN
	 * @author  Patroklo
	 */
	function is_file_object($object) 
	{
		return !is_null($object['upload_parent']);
	}
	
	
	/**
	 * called from set_options, checks the upload fields data and makes a new 
	 * object to hold it in case it's a field object child of another object
	 * like => user -> user_photo (the field will be declared in user but here
	 * 							   we will change that and add it in user_photo object)
	 *
	 * @return void
	 * @author  
	 */
	
	protected function set_upload(&$field_data)
	{
		$object_name 	= $field_data['options']['object_type'];
		//$field_object 	= $this->objects[$object_name]['data']->_get_loaded_file($field_data['options']['fieldName']);
		
		// get the parent field FILE definition in order to get the data description and so on
		$objectClassData 	= $this->correcaminos->get_class_data($object_name)->get_files();
		

		// if it doesn't exist the file field or the field in the objectClassData, it may be a independent upload
		// without its parent object in the form. That implies that the object should be loaded with a valid parent or 
		// reference_id in the save state or will throw an exception
		
		if(is_array($objectClassData) && array_key_exists($field_data['options']['fieldName'], $objectClassData))
		{
			$fileClassData 		= $objectClassData[$field_data['options']['fieldName']];
			
			// if rules are not setted, will use the parent object rules
			
			if(!array_key_exists('rules',$field_data['options']))
			{
				$field_data['options']['rules'] = $fileClassData['rules'];
			}
		
			// creates a new object, the upload object with the data defined in the field
			
			$new_object_type = $object_name.'_'.$objectClassData[$field_data['options']['fieldName']]['className'];

			$this->create_object(array( 'name' 			=> $field_data['options']['fieldName'],
										'alias'			=> $new_object_type,
										'upload_parent'	=> $object_name
								));
			

			$this->_check_object_loaded_file($new_object_type);

			$field_data['options']['object_type'] = $new_object_type;	
		}
	}

	protected function _check_object_loaded_file($object_alias)
	{
		$file_object = $this->objects[$object_alias];
		$object_name = $file_object['upload_parent'];
		
		if($this->objects[$object_name]['data'] !== NULL)
		{
			$loaded_file = $this->objects[$object_name]['data']->_get_loaded_file($file_object['name']);
			
			if($loaded_file != FALSE)
			{
				$this->objects[$object_alias]['data'] = $loaded_file;
			}
		}
	}
	
	
	protected function _save_object($object)
	{
		if($this->is_file_object($object))
		{
			// check if there is a reference if already defined in the upload object,
			// if not, try to set one
			$config_data = $object['data']->get_config_data();
			
			if(is_null($config_data['reference_id']))
			{
				$object['data']->set_parent($this->objects[$object['upload_parent']]['data']);
			}
			
		}
		
		$object['data']->save();
	}
	
	protected function update($object_key)
	{
		$object = $this->objects[$object_key];
		
		$this->_save_object($object);
	}
	
	
	protected function insert($object_key)
	{
		$object = $this->objects[$object_key];
		
		$this->_save_object($object);
	}
	
	/**
	 * In this version of the class the method checks every object to see if it's in insert
	 * or update state, then it calls the insert or update method with the object
	 * 
	 * @return void
	 * @author  Patroklo
	 */

	function save($data = NULL)
	{
		if($data === NULL)
		{
			$data = $this->post_data;
		}
		
		$data = $this->sanitize_data($data);
		
		// check each object if it's initialized. If it's not, then
		// create a new empty object for it.

		foreach($this->objects as $key => &$object)
		{
			if($object['data'] === NULL)
			{
				// creates a new object with insert state
				$object['data'] = $this->correcaminos->new_object($object['name']);
			}
		}
		
		unset($object);
		
		// check every $_post data for object values
		// if there are, it adds the new value to them
		foreach($data as $field_name => $field_value)
		{
			$field_options = $this->fields[$field_name]->get_options();
			
			if(array_key_exists('object_type', $field_options))
			{
				$object_name 		= $field_options['object_type'];
				$field_object_name 	= $field_options['fieldName'];
				
				$this->objects[$object_name]['data']->set_data($field_object_name, $field_value);
			}
		}
		
		// now calls update or insert depending of each object

		foreach($this->objects as $key => $object)
		{

			if($object['data']->_get_state() == 'INSERT')
			{
				$this->insert($key);
			}
			// it will enter here if the object it's in update or delete state
			else
			{
				$this->update($key);
			}
		}
		
	}


	function carga($object_alias, $filter = NULL)
	{
		if($filter === NULL and count($this->objects) > 1)
		{
			throw new Exception("You can't define a carga method without a filter.", 1);
		}
		elseif($filter === NULL and count($this->objects) == 1)
		{
			$filter 		= $object_alias;
			$object_alias 	= key($this->objects);
		}
		
		if(!array_key_exists($object_alias, $this->objects))
		{
			throw new Exception("The object ".$object_alias." it's not defined in the form.", 1);
		}
		
		if(is_object($filter))
		{
			$this->objects[$object_alias]['data'] = $filter;
		}
		else
		{
			if(is_numeric($filter))
			{
				$filter = array('id' => $filter);
			}
			
			$this->objects[$object_alias]['data'] = beep($this->objects[$object_alias]['name'])->where($filter)->get_one();
			
			
			if($this->objects[$object_alias]['data'] == FALSE)
			{
				throw new Exception("The object ".$object_alias." could not be loaded.", 1);
			}

		}
		
		// check if it's an object with a file that might be uploaded
		
		foreach($this->objects as $key => $object)
		{
			if($this->is_file_object($object))
			{
				$this->_check_object_loaded_file($key);
			}
		}
	}
	
	function get_object($object_alias)
	{
		if(!array_key_exists($object_alias, $this->objects))
		{
			return FALSE;
		}
		
		$return_object = $this->objects[$object_alias]['data'];
		
		if(is_null($return_object))
		{
			$return_object = FALSE;
		}
		
		return $return_object;
		
	}
	
	
	function show_field($field_name)
	{
		$field = $this->fields[$field_name];
		
		$field_options = $this->fields[$field_name]->get_options();
		
		$optional_value = NULL;
		
		if(array_key_exists('object_type', $field_options))
		{
			$object_name 		= $field_options['object_type'];
			$field_object_name 	= $field_options['fieldName'];

			if(!empty($this->objects[$object_name]['data']) && !$this->is_file_object($this->objects[$object_name]))
			{
				$optional_value	= $this->objects[$object_name]['data']->get_data($field_object_name);
			}
			
		}
				
		$field->set_value(set_value($field_name, $optional_value));
		
		return $field->show();
	}
	
	
	 function add_rules($rules = NULL)
	 {
	 	$config = array();
		
		if(empty($this->rules))
		{
		 	foreach($this->fields as $key => $field)
			{
				$field_options = $field->get_options();
			
				if(array_key_exists('object_type', $field_options))
				{
					$object_name 		= $field_options['object_type'];
					
					if($this->objects[$object_name]['data'] !== NULL)
					{
						$this->set_loaded($this->objects[$object_name]['data']->_object_loaded());
					}
				}

				$config[$field->get_id()] = array('field' => $field->get_name(), 'label' => $field->get_name(), 'rules' => $field->get_rules($this->is_loaded()));
			}
		}
		
		if(!empty($rules) && is_array($rules))
		{
			foreach($rules as $key => $rule)
			{
				$config[$key] = $config[$key] + $rule;
			}
		}
		
		$this->rules = $config;
	 }
	
}