<?php namespace components\mail\models; if(!defined('TX')) die('No direct access.');

class Subscriptions extends \dependencies\BaseModel
{
  
  const
    SUBSCRIPTION_UNKNOWN = 'UNKNOWN',
    SUBSCRIPTION_SUBSCRIBED = 'SUBSCRIBED',
    SUBSCRIPTION_UNSUBSCRIBED = 'UNSUBSCRIBED';
  
  protected static
    
    $table_name = 'mail_subscriptions';
  
  public function is_unsubscribed()
  {
    return $this->subscription->get() === self::SUBSCRIPTION_UNSUBSCRIBED;
  }
  
  public function as_normalized_email()
  {
    return Data(array(
      'email' => $this->email,
      'name' => $this->name
    ));
  }
  
  //Overrides normal save.
  public function save()
  {
    
    //Generate a unique key if it doesn't exist yet.
    $this->key->is('empty', function($key){
      
      do{
        
        $new_key = tx('Security')->random_string();
        $unique = tx('Sql')
          ->table('mail', 'Subscriptions')
          ->where('key', "'{$new_key}'")
          ->count()->get('int') === 0;
        
      }
      while($unique == false);
      
      $key->set($new_key);
      
    });
    
    return parent::save();
    
  }
  
}
