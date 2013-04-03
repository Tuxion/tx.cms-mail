<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

class Views extends \dependencies\BaseViews
{
  
  protected function mailing()
  {
    
    return array(
      'editor' => $this->section('mailing_editor'),
      'history' => $this->section('mailing_history')
    );
    
  }
  
}
