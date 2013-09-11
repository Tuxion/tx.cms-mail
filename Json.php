<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

//Preload the mailing recipients class, then bind it.
tx('Sql')->model('mail', 'MailingRecipients');

use \components\mail\classes\MailForm;
use \components\mail\models\MailingRecipients;

class Json extends \dependencies\BaseComponent
{
  
  protected $permissions = array(
    'create_form_entry' => 0
  );
  
  protected function create_form_entry($data, $params)
  {
    
    $form = MailForm::get($params->{0})
      ->set_data($data);
    
    $html = $form->to_html();
    
    $this->helper('send_fleeting_mail', array(
      'to' => $form->get_recipient(),
      'from' => $form->get_sender(),
      'subject' => $form->get_subject(),
      'html_message' => $html
    ));
    
    return mk('Sql')
      ->model('mail', 'FormEntries')
      ->set(array(
        'subject' => $form->get_subject(),
        'recipient' => $form->get_recipient(),
        'mail_contents' => $html
      ))
      ->validate_model(array(
        'force_create' => true
      ))
      ->save()
      ->is(true, function()use($form){
        $form->remove();
      });
    
  }
  
  protected function update_mailing($data, $parameters)
  {
    
    #To do a push on Data object that respects the type, use __get(null) to do auto-increment and become().
    
    //Collect data to register.
    $mailing = $data->having('id', 'subject', 'message');
    
    //Format the arrays to MailingRecipient models.
    $data->recipient_users->each(function($user)use($mailing){
      $mailing->recipients->__get(null)->become(
        tx('Sql')->model('mail', 'MailingRecipients')->merge(array(
          'type' => MailingRecipients::TYPE_USER,
          'recipient_id' => $user->get()
        ))
      );
    });
    $data->recipient_groups->each(function($group)use($mailing){
      $mailing->recipients->__get(null)->become(
        tx('Sql')->model('mail', 'MailingRecipients')->merge(array(
          'type' => MailingRecipients::TYPE_GROUP,
          'recipient_id' => $group->get()
        ))
      );
    });
    
    //Format the arrays to MailingTesters models.
    $data->tester_users->each(function($user)use($mailing){
      $mailing->testers->__get(null)->become(
        tx('Sql')->model('mail', 'MailingTesters')->merge(array(
          'type' => MailingRecipients::TYPE_USER,
          'recipient_id' => $user->get()
        ))
      );
    });
    $data->tester_groups->each(function($group)use($mailing){
      $mailing->testers->__get(null)->become(
        tx('Sql')->model('mail', 'MailingTesters')->merge(array(
          'type' => MailingRecipients::TYPE_GROUP,
          'recipient_id' => $group->get()
        ))
      );
    });
    
    //Store this information in the mailing.
    $Mailing = $this->helper('register_mailing', $mailing);
    
    //Now perform the requested command.
    return $Mailing->perform_command($data->command->get());
    
  }

}
