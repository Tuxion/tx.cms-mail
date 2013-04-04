<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

class Modules extends \dependencies\BaseViews
{
  
  protected function unsubscribe($options)
  {
    
    return tx('Sql')
      ->table('mail', 'Subscriptions')
      ->where('key', "'{$options->key}'")
      ->execute_single()
      ->is('empty', function(){
        throw new \exception\NotFound('No subscription found with this key');
      });
    
  }
  
}
