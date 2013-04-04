<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

//Preload and bind models to have easy constant access.
tx('Sql')->model('mail', 'Subscriptions');
use \components\mail\models\Subscriptions;

class Actions extends \dependencies\BaseComponent
{
  
  protected function unsubscribe($options)
  {
    
    //Do the unsubscribe.
    if($options->confirm->is_set()){
      
      tx('Logging')->log('Mailing component', 'Unsubscribe confirmation', $options->dump());
      
      tx('Sql')
        ->table('mail', 'Subscriptions')
        ->where('key', "'{$options->key}'")
        ->execute_single()
        ->not('empty', function($s){$s
            ->merge(array(
              'subscription' => Subscriptions::SUBSCRIPTION_UNSUBSCRIBED,
              'dt_unsubscribed' => date('Y-m-d H:i:s')
            ))
            ->save()
        ;});
      
      tx('Url')->redirect(url('?module=mail/unsubscribe&options[key]='.urlencode($options->key->get()), true));
      
    }
    
    //Cancel the unsubscribe.
    elseif($options->cancel->is_set()) {
      tx('Url')->redirect(url(tx('Config')->user('homepage'),true));
    }
    
    //Show the confirmation page.
    else{
      #TODO: We would want to change this later to some place where you have a full template.
      tx('Url')->redirect(url('?module=mail/unsubscribe&options[key]='.urlencode($options->key->get()), true));
    }
    
  }
  
}
