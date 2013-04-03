<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

class Sections extends \dependencies\BaseViews
{
  
  protected function mailing_editor($options)
  {
    
    $templates = tx('Sql')
      ->table('mail', 'Templates')
      ->order('title')
      ->execute();
    
    return array(
      'templates' => $templates,
      'template' => $templates->size() == 1 ? $templates->{0} : null,
      'mailing' => $options->mailing_id->is_set() ?
        tx('Sql')
          ->table('mail', 'Mailings')
          ->pk($options->mailing_id)
          ->execute_single()
        : null
    );
    
  }
  
  protected function mailing_history()
  {
    
    return tx('Sql')
      ->table('mail', 'Mailings')
      ->order('dt_created', 'DESC')
      ->execute();
    
  }
  
}
