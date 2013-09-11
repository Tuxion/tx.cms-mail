<?php namespace components\mail\models; if(!defined('TX')) die('No direct access.');

class FormEntries extends \dependencies\BaseModel
{
  
  protected static
    
    $table_name = 'mail_form_entries',
    
    $validate = array(
      'id' => array('number'=>'int', 'gt'=>0),
      'subject' => array('required', 'string', 'not_empty'),
      'recipient' => array('required', 'string', 'not_empty'),
      'mail_contents' => array('required', 'string', 'not_empty')
    );
    
  
}
