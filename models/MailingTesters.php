<?php namespace components\mail\models; if(!defined('TX')) die('No direct access.');

//Make sure parent class is loaded.
tx('Sql')->model('mail', 'MailingRecipients');

class MailingTesters extends MailingRecipients
{
  
  protected static
    
    $table_name = 'mail_mailing_testers';
  
}
