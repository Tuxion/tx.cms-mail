<?php namespace components\mail\models; if(!defined('TX')) die('No direct access.');

//Preload the mailing recipients class.
tx('Sql')->model('mail', 'MailingRecipients');

class Mailings extends \dependencies\BaseModel
{
  
  const
    STATE_DRAFTING = 'DRAFTING',
    STATE_TESTING = 'TESTING',
    STATE_ERROR = 'ERROR',
    STATE_SENT = 'SENT';
  
  const
    COMMAND_DRAFT = 'DRAFT',
    COMMAND_TEST = 'TEST',
    COMMAND_SEND = 'SEND';
  
  protected static
    
    $table_name = 'mail_mailings';
    
    /*$validate = array(
      'email' => array('required', 'email'),
      'username' => array('string', 'between'=>array(0, 255), 'no_html'),
      'name' => array('string', 'between'=>array(0, 255), 'no_html'),
      'preposition' => array('string', 'between'=>array(0, 255), 'no_html'),
      'family_name' => array('string', 'between'=>array(0, 255), 'no_html'),
      'comments' => array('string', 'no_html')
    );*/
  
  public function get_state_title()
  {
    
    return __($this->component(), 'STATE_'.$this->state, true);
    
  }
  
  public function get_testers()
  {
    
    return tx('Sql')
      ->table('mail', 'MailingTesters')
      ->where('mailing_id', $this->id)
      ->execute();
    
  }
  
  public function get_recipients()
  {
    
    return tx('Sql')
      ->table('mail', 'MailingRecipients')
      ->where('mailing_id', $this->id)
      ->execute();
    
  }
  
  public function perform_command($command)
  {
    
    //Explicitly fetch the state again.
    $state = tx('Sql')
      ->table('mail', 'Mailings')
      ->pk($this->id)
      ->execute_single()
      ->state;
    
    if($state->get() === self::STATE_SENT)
      throw new \exception\User('Can\'t perform commands on a mailing that is in the %s state', $this->get_state_title());
    
    switch($command){
      
      //Drafting command is easy.
      //We assume all data is saved by register_mailing and simply save the state.
      case self::COMMAND_DRAFT:
        $this->state->set(self::STATE_DRAFTING);
        $this->save();
        break;
      
      //Testing command.
      //Saves the mailing in testing state, regardless of success but won't force a send.
      case self::COMMAND_TEST:
        $this->state->set(self::STATE_TESTING);
        $this->save();
        $this->send_to('testers');
        break;
      
      //Send command.
      //Saves the mailing in error state, sends and if successfully it updates to sent state.
      case self::COMMAND_SEND:
        $this->state->set(self::STATE_ERROR);
        $this->save();
        $this->send_to('recipients');
        $this->state->set(self::STATE_SENT);
        $this->save();
        break;
        
      default:
        throw new \exception\User('Unknown command "%s"', $command);
      
    }
    
    return $this;
    
  }
  
  public function normalize_addresses($for='recipients')
  {
    
    $resultset = Data();
    
    //Collect all and merge on email address as key.
    foreach($this->{$for} as $recipients)
      $resultset->merge($recipients->normalized_addresses);
    
    return $resultset;
    
  }
  
  private function send_to($to)
  {
    
    //Mailers only validate, so store them for later.
    $mailers = Data();
    
    foreach($this->normalize_addresses($to) as $address){
      
      //Validate email through the helper.
      tx('Component')->helpers('mail')->send_fleeting_mail(array(
        'to' => $address,
        'subject' => $this->subject,
        'html_message' => $this->message,
        'validate_only' => true
      ))
      
      ->failure(function($info){
        throw $info->exception;
      })
      
      //If it's ok, store the mailer.
      ->success(function($info)use($mailers){
        $mailers->push($info->return_value);
      });
      
    }
    
    //After all mail was validated, send it.
    $mailers->each(function($mailer){
      try{
        $mailer->get()->Send();
      }catch(\Exception $e){
        throw new \exception\Programmer('Fatal error sending email. Exception message: %s', $e->getMessage());
      }
    });
    
  }
  
}
