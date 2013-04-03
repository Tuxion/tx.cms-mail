<?php namespace components\mail\models; if(!defined('TX')) die('No direct access.');

class MailingRecipients extends \dependencies\BaseModel
{
  
  const
    TYPE_USER = 'USER',
    TYPE_GROUP = 'GROUP';
  
  protected static
    $table_name = 'mail_mailing_recipients';
  
  public function get_mailing()
  {
    return tx('Sql')
      ->table('mail', 'Mailings')
      ->pk($this->mailing_id)
      ->execute_single();
  }
  
  public function get_recipient_model()
  {
    
    if($this->type->get() === self::TYPE_USER){
      
      return tx('Sql')
        ->table('account', 'Accounts')
        ->pk($this->recipient_id)
        ->execute_single();
      
    }
    
    elseif($this->type->get() === self::TYPE_GROUP){
      
      return tx('Sql')
        ->table('account', 'UserGroups')
        ->pk($this->recipient_id)
        ->execute_single();
      
    }
    
  }
  
  public function get_name()
  {
    
    if($this->type->get() === self::TYPE_USER){
      
      return $this->recipient_model
        ->not('empty', function($user){
          return $user->user_info->full_name->otherwise($user->email);
        });
      
    }
    
    elseif($this->type->get() === self::TYPE_GROUP){
      
      return $this->recipient_model
        ->not('empty', function($group){
          return __('Group', 1).': '.$group->title->get().' ('.$group->users->size().')';
        });
      
    }
    
  }
  
  public function get_label()
  {
    
    if($this->type->get() === self::TYPE_USER){
      
      return $this->recipient_model
        ->not('empty', function($user){
          return $user->user_info->full_name->not('empty', function($full_name)use($user){
            return $full_name->get().' <'.$user->email->get().'>';
          })->otherwise($user->email);
        });
      
    }
    
    elseif($this->type->get() === self::TYPE_GROUP){
      
      return $this->recipient_model
        ->not('empty', function($group){
          return __('Group', 1).': '.$group->title->get().' ('.$group->users->size().')';
        });
      
    }
    
  }
  
  public function get_normalized_addresses()
  {
    
    if($this->type->get() === self::TYPE_USER)
      return tx('Component')->helpers('mail')->_call('normalize_email_input', array($this->label));
    
    elseif($this->type->get() === self::TYPE_GROUP){
      
      $recipients = array();
      foreach($this->recipient_model->users as $user){
        $recipients[] = array(
          'name' => $user->user_info->full_name,
          'email' => $user->email
        );
      }
      return tx('Component')->helpers('mail')->_call('normalize_email_input', array($recipients));
      
    }
    
  }
  
  //Compares this MailingRecipients model to another one.
  public function is_equal(MailingRecipients $value)
  {
    
    if($value->recipient_id->get() !== $this->recipient_id->get())
      return false;
    
    if($value->type->get() !== $this->type->get())
      return false;
    
    if($value->mailing_id->get() !== $this->mailing_id->get())
      return false;
    
    return true;
    
  }
  
}
