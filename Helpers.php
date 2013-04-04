<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

//Preload and bind models to have easy constant access.
tx('Sql')->model('mail', 'Mailings');
use \components\mail\models\Mailings;

tx('Sql')->model('mail', 'Subscriptions');
use \components\mail\models\Subscriptions;

class Helpers extends \dependencies\BaseComponent
{
  
  /**
   * Takes an array of normalized email addresses and returns their corresponding Subscription models.
   *
   * @param Data $data->addresses An array of normalized e-mail addresses.
   * @param Boolean $data->auto_subscribe Whether to automatically add a subscription for new e-mail addresses.
   * @param Boolean $data->filter_unsubscribed Whether to automatically filter out addresses that unsubscribed.
   * @return Data An array of their corresponding Subscription models.
   */
  public function normalized_email_to_subscriptions($data)
  {
    
    $result = Data();
    foreach($data->addresses as $index => $address){
      
      $model = tx('Sql')
        ->table('mail', 'Subscriptions')
        ->where('email', "'{$address->email}'")
        ->execute_single()
        
        //In case it's not in the database, generate it.
        ->is('empty', function()use($address, $data){
          
          return tx('Sql')
            ->model('mail', 'Subscriptions')
            ->merge($address->having('email', 'name'))
            
            //See if this model should be auto-subscribed.
            ->is($data->auto_subscribe->is_true(), function($subscription){
              $subscription->merge(array('subscription' => Subscriptions::SUBSCRIPTION_SUBSCRIBED));
            })
            
            ->save();
          
        });
      
      if($data->filter_unsubscribed->is_true() && $model->is_unsubscribed()){
        continue;
      }
      
      //Uses __get(null)->become() to prevent the model from being converted to a Data object.
      $result->__get(null)->become($model);
      
    }
    return $result;
    
  }
  
  /**
   * Creates / updates a mailing that is meant to be stored and retrievable.
   * Note: since this function includes minimal validation so that the saving can be done easily.
   *
   * @author Beanow
   * @param Integer $data->id The ID of an existing mailing.
   * @param String $data->subject The subject for the mailing.
   * @param String $data->message The html message for the mailing.
   * @param String $data->state The state value. Possible values: [DRAFTING|TESTING|SENT] (default: DRAFTING).
   * @param MailingRecipients[] $data->recipients A list of recipients for this mailing (mailing_id is ignored).
   * @param MailingTesters[] $data->testers A list of testers for this mailing (mailing_id is ignored).
   * @return Mailing The stored mailing object with all associated testers and recipients.
   */
  public function register_mailing(\dependencies\Data $data)
  {
    
    //Filter and validate input.
    $data = $data->having('id', 'subject', 'message', 'state', 'recipients', 'testers')
      ->id->validate('ID', array('number'=>'integer', 'gt'=>0))->back()
      ->subject->validate('Subject', array('required', 'string', 'no_html'))->back()
      ->message->validate('Message', array('required', 'string'))->back()
      ->state->validate('State', array('string', 'in'=>array(
          Mailings::STATE_DRAFTING,
          Mailings::STATE_TESTING,
          Mailings::STATE_ERROR,
          Mailings::STATE_SENT
        )))->back()
      ->recipients->validate('Recipient(s)', array('array'))->back()
      ->testers->validate('Tester(s)', array('array'))->back();
    
    tx('Logging')->log('Mailing component', 'Register mailing called', $data->dump());
    
    //Initiate blank model.
    $Mailing = tx('Sql')->model('mail', 'Mailings');
    
    //Try and get the existing mailing.
    if($data->id->is_set()){
      
      tx('Sql')
        ->table('mail', 'Mailings')
        ->pk($data->id)
        ->execute_single()
        ->is('set', function($existing)use(&$Mailing){
          $Mailing = $existing;
        });
      
    }
    
    //Merge and save the mailing model with input data.
    $Mailing
      ->merge($data->having('state', 'subject', 'message'))
      ->save();
    
    //Function to merge and save old recipients with new recipients, that outputs a result collection.
    $mergeRecipients = function($result, $old, $new, $mailing_id){
      
      //Clear the target.
      $result->un_set();
      
      //Clone the input.
      $old = $old->copy();
      $new = $new->copy();
      
      //Cross-match old with new, to find which ones are to be kept.
      $old->each(function($old_item)use($new, $result, $mailing_id){
        $new->each(function($new_item)use($old_item, $result, $mailing_id){
          
          //Before we match it, force the mailing_id to the one we have now.
          $new_item->merge(array('mailing_id'=>$mailing_id));
          
          //When we have a match from the old and new, store it in the results.
          if($old_item->is_equal($new_item)){
            
            $result->__get(null)->become($old_item->copy());
            $old_item->un_set();
            $new_item->un_set();
            
          }
          
        });
      });
      
      //Delete all remaining old ones.
      $old->each(function($imgoingbyebye){
        
        //Skip the unset ones.
        if(!$imgoingbyebye->is_set())
          return true;
        
        $imgoingbyebye->delete();
        
      });
      
      //Append all the remaining new ones to the results.
      $new->each(function($add_me)use($result, $mailing_id){
        
        //Skip the unset ones.
        if(!$add_me->is_set())
          return true;
        
        //Before we add it, force the mailing_id to the one we have now.
        $add_me->merge(array('mailing_id'=>$mailing_id));
        
        $result->__get(null)->become($add_me);
        $add_me->save();
        
      });
      
    };
    
    //Merge testers and recipients with old data.
    $Recipients = Data();
    $Testers = Data();
    $mergeRecipients($Recipients, $Mailing->recipients, $data->recipients, $Mailing->id->get());
    $mergeRecipients($Testers, $Mailing->testers, $data->testers, $Mailing->id->get());
    
    //Save the current result states.
    $Recipients->each(function($recipient){
      $recipient->save();
    });
    $Testers->each(function($recipient){
      $recipient->save();
    });
    
    //Return the mailing, with the collected recipients and testers.
    return $Mailing->merge(array(
      'recipients' => $Recipients,
      'testers' => $Testers
    ));
    
  }
  
  /**
   * Sends an email that is not meant to be stored or retrievable.
   * Note: mailing groups are not supported.
   *
   * @author Beanow
   * @param Email $data->to The email addresses to send the email to.
   * @param Email $data->cc The email addresses to send cc's to.
   * @param Email $data->bcc The email addresses to send bcc's to.
   * @param Email $data->from The email address to send the email from.
   * @param Email $data->reply_to The email address to reply to.
   * @param String $data->subject The subject for the email.
   * @param HTML $data->html_message The email message body.
   * @param Array $data->attachments A multidimentional array of attachments in the following format: {n:{filename[, attachment_name]}}
   * @param Data $data->headers A Data array of email headers to set.
   * @param Boolean $data->debug If true, email is not sent but stored in a debug file.
   * @param Boolean $data->validate_only If true, email is not sent but only validated.
   * @return \dependencies\UserFunction Returns the user function in which the email is sent and it's return_value is the PHPMailer instance.
   */
  public function send_fleeting_mail($data)
  {
    
    //Apply default values.
    $data = Data(array(
      'from' => '"'.EMAIL_NAME_AUTOMATED_MESSAGES.'" <'.EMAIL_ADDRESS_AUTOMATED_MESSAGES.'>',
      'attachments' => array(),
      'use_html' => true,
      'headers' => array(),
      'debug' => false
    ))->merge(Data($data))
      ->having('to', 'cc', 'bcc', 'from', 'reply_to', 'subject', 'html_message', 'attachment', 'headers', 'debug', 'validate_only');
    
    tx('Logging')->log('Mail', 'Send fleeting mail', 'With data: '.$data->dump());
    
    //Normalize (and validate) email addresses.
    $data->from     = $this->normalize_email_input($data->from, false); //¬_¬ Yes this is not standards compliant. Blame PHPMailer.
    $data->reply_to = $this->normalize_email_input($data->reply_to, true);
    $data->to       = $this->normalize_email_input($data->to, true);
    $data->cc       = $this->normalize_email_input($data->cc, true);
    $data->bcc      = $this->normalize_email_input($data->bcc, true);
    
    //Check if we have at least one recipient.
    if($data->to->is_empty() && $data->cc->is_empty() && $data->bcc->is_empty())
      throw new \exception\Validation("You must provide at least one recipient.");
    
    //Validate other data.
    $data
      ->subject->validate('Subject', array('required', 'string', 'no_html', 'not_empty'))->back()
      ->html_message->validate('HTML message', array('required', 'string', 'not_empty'))->back()
      ->headers->validate('Headers', array('array'))->back()
      ->validate_only->validate('Validate only setting', array('boolean'))->back()
      ->debug->validate('Debug setting', array('boolean'))->back();
    
    //Use PHPMailer plugin to send mail.
    load_plugin('phpmailer');
    $mailer = new \plugins\PHPMailer(true); //True is for throwing exceptions.
    
    return tx('Sending email', function()use($mailer, $data){
      
      try
      {
        
        //Function to add all (normalized) email addresses in a list to the PHPMailer.
        $add_all_to = function($to)use($data, $mailer)
        {
          
          //Get a target and a source in the Data object.
          $to = strtolower($to);
          $src = $to;
          
          //Make the target compliant with PHPMailer.
          switch($to){
            case 'to':
              $to = 'AddAddress';
              break;
            case 'cc':
            case 'bcc':
              $to = 'Add'.strtoupper($to);
              break;
            case 'reply_to':
              $to = 'AddReplyTo';
              break;
            case 'from':
              $to = 'SetFrom';
              break;
          }
          
          //Itterate over the items and call the associated function on the PHPMailer object.
          $data->{$src}->each(function($address)use($mailer, $to){
            $mailer->{$to}($address->email, $address->name);
          });
          
        };
        
        //Call the function on the five supported settings.
        $add_all_to('from');
        $add_all_to('reply_to');
        $add_all_to('to');
        $add_all_to('cc');
        $add_all_to('bcc');
        
        //Continue with other data.
        $mailer->Subject = $data->subject->get('string');
        $mailer->MsgHTML($data->html_message->get('string'));
        
        $data->attachments->each(function($attachment)use($mailer){
          $mailer->AddAttachment($attachment->{0}, $attachment->{1}->otherwise(''));
        });
        
        //When we should wait with sending and are actually validating.
        if($data->validate_only->is_true()){
          
          //Invoke the PreSend method, this opposed to Send will only generate the email and not send it.
          //The actual sending is done in PostSend.
          $preSendMethod = new \ReflectionMethod('\plugins\PHPMailer', 'PreSend');
          $preSendMethod->setAccessible(true);
          $preSendMethod->invoke($mailer);
          return $mailer;
          
        }
        
        //When we're actually sending.
        else{
        
          //If we're debugging. Save a copy of the mail in a local file.
          if($data->debug->get('boolean'))
          {
            
            //Invoke the PreSend method, this opposed to Send will only generate the email and not send it.
            //The actual sending is done in PostSend.
            $preSendMethod = new \ReflectionMethod('\plugins\PHPMailer', 'PreSend');
            $preSendMethod->setAccessible(true);
            $preSendMethod->invoke($mailer);
            
            $debug_output_dir = PATH_COMPONENTS.DS.'mail'.DS.'debug';
            
            if(!file_exists($debug_output_dir))
              @mkdir($debug_output_dir);
            
            $f = @fopen($debug_output_dir.DS.'mail_'.time().'_'.sha1($mailer->GetSentMIMEMessage()).'.eml', 'w');
            @fwrite($f, $mailer->GetSentMIMEMessage());
            @fclose($f);
            return $mailer;
            
          }
          
          //Send the email!
          else{
            //WOOOOOO!
            //Never thought sending an email could be this complex huh?
            $mailer->Send();
            return $mailer;
          }
        
        }
        
      } catch(\Exception $e) {
        if($data->validate_only->is_true())
          throw new \exception\Validation('Email data is not valid. Exception message: %s', $e->getMessage());
        else
          throw new \exception\Programmer('Could not send email. Exception message: %s', $e->getMessage());
      }
      
    });
    
  }
  
  /**
   * Normalizes and validates email input.
   * Note: mailing groups are not supported.
   * Accepted input:
   *   1) {#:{name:'name', email:'email'}}
   *   2) {#:'Name <email>'}
   *   3) {#:'email'}
   *   4) 'Name <email>, Name <email>'
   *   5) 'email, email'
   *   6) {name:'name', email:'email'}
   *   7) 'Name <email>'
   *   8) 'email'
   *
   * @author Beanow
   * @param mixed $input The input that is supposed to be email input.
   * @param Boolean $allow_multiple Whether to allow multiple email addresses to be included.
   * @return Array A normalized Data array of email addresses in the format: {'email':{name:'name', email:'email'}}
   */
  public function normalize_email_input($input, $allow_multiple=true)
  {
    
    $input = data_of($input);
    $helper = $this;
    
    if($input == null)
      return null;
    
    //The function to use to do a recursive call.
    $reitterate = function($input)use($helper, $allow_multiple)
    {
      
      //Check if we're allowed to use multiples.
      if($allow_multiple !== true)
        throw new \exception\Validation('Email input is malformatted: multiple email addresses supplied where not allowed.');
      
      $output = Data();
      foreach($input as $row)
      {
        
        //Expected format:
        //  {0:{name:'Name', email:'email@domain.net'}}
        $helper->normalize_email_input($row, false)
          ->each(function($pair)use($output){
            $output->{$pair->email->get('string')}->set($pair);
          });
        
      }
      
      return $output;
      
    };
    
    //Find the type we are dealing with.
    switch(ucfirst(gettype($input)))
    {
      
      case 'Array':
        //A name and email pair
        if(isset($input['name']) && isset($input['email']))
        {
          
          /* ---------- Format type: 6 ---------- */
          return Data(array((string)$input['email'] => array(
            'name'=>$input['name'],
            'email'=>$input['email']
          )))
          ->{$input['email']}
            ->name->validate('Name', array('string'))->back()
            ->email->validate('Email', array('required', 'email'))->back()
          ->back();
          
        }
        
        
        /* ---------- Format type: 1, 2, 3 ---------- */
        return $reitterate($input);
        break;
        
      case 'String':
        //First we trim and replace breaks.
        $input = trim(preg_replace('/[\r\n]+/', '', $input));
        
        //If we have an empty string return just that.
        if(strlen($input) == 0)
          return '';
        
        //Check if we have an EXACT format 7 (Name <email>).
        //If it is a multiple we need to split later on so don't match here!
        if(preg_match('@^"?([a-zA-Z0-9!#$%&\'*+-/=?\^_`{|}~ ]+)"? <([^>]+)>$@', $input, $matches) == 1)
        {
          
          /* ---------- Format type: 7 ---------- */
          return Data(array((string)$matches[2] => array(
            'name' => $matches[1], //Name is now validated by regex.
            'email' => Data($matches[2])->validate('Email', array('required', 'email'))->get() //Email needs another validation pass.
          )));
          
        }
        
        //Split on comma's.
        $parts = explode(',', $input);
        if(count($parts) > 1)
        {
          
          /* ---------- Format type 4, 5 ---------- */
          return $reitterate($parts);
          
        }
        
        //Check if we have an EXACT format 8 (email).
        else {
          /* ---------- Format type: 8 ---------- */
          return Data(array((string)$input => array(
            'name' => '',
            'email' => Data($input)->validate('Email', array('required', 'email'))->get()
          )));
        }
        
        break;
        
      default:
        throw new \exception\InvalidArgument('Invalid email format. Datatype %s is not supported.', gettype($input));
        break;
      
    }
    
  }
  
}
