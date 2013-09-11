<?php namespace components\mail\classes; if(!defined('MK')) die('No direct access.');

class MailForm
{
  
  /**
   * Gets a form instance from the session based on it's ID.
   * @param  string $id The ID for the form.
   * @return MailForm The requested MailForm.
   */
  public static function get($id)
  {
    
    raw($id);
    
    if(mk('Data')->session->mail_forms->{$id}->is_empty())
      throw new \exception\NotFound('The form with ID "%s" was not found. Did your session expire?', $id);
    
    $data = mk('Data')->session->mail_forms->{$id};
    
    return new MailForm($data->subject->get(), $data->recipient->get(), $data->fields->get(), $data->sender->get(), $id);
    
  }
  
  protected
    $recipient,
    $sender,
    $subject,
    $fields,
    $form_id,
    $form_data;
  
  /**
   * Creates a new MailForm.
   * @param string  $subject   The subject of the mail.
   * @param mixed   $recipient The recipient of the form contents (will be normalized).
   * @param array   $fields    The field definitions that will function as the forms contents.
   * @param mixed   $sender    An optional sender address (will be normalized).
   * @param mixed   $form_id   The form ID to use, if empty a new one will be created.
   */
  public function __construct($subject, $recipient, $fields, $sender=null, $form_id=null)
  {
    
    raw($form_id);
    
    mk('Component')->helpers('mail')->call('send_fleeting_mail', array(
      'to' => $recipient,
      'from' => $sender,
      'subject' => $subject,
      'html_message' => 'Hello world!',
      'validate_only' => true
    ))
    ->failure(function($info){
      throw new \exception\Validation($info->get_user_message());
    });
    
    //Store the supplied information in the class.
    $this->subject = $subject;
    $this->recipient = $recipient;
    $this->fields = $fields;
    $this->sender = $sender;
    
    //When generating a new ID, perform a store.
    if($form_id == null){
      $this->store();
    } else {
      $this->form_id = $form_id;
    }
    
  }
  
  //Getters
  public function get_form_id(){
    return $this->form_id;
  }
  
  public function get_subject(){
    return $this->subject;
  }
  
  public function get_recipient(){
    return $this->recipient;
  }
  
  public function get_fields(){
    return $this->fields;
  }
  
  public function get_sender(){
    return empty($this->sender) ? '"'.EMAIL_NAME_AUTOMATED_MESSAGES.'" <'.EMAIL_ADDRESS_AUTOMATED_MESSAGES.'>' : $this->sender;
  }
  
  /**
   * Sets the raw form data as submitted by the user.
   * @param \dependencies\Data $data The raw form data submitted by the user.
   * @return $this Enables chaining.
   */
  public function set_data($data)
  {
    
    if(isset($this->form_data))
      throw new \exception\Programmer('Form data has already been set for this instance of the form.');
    
    $this->form_data = $data;
    
    $i=0;
    foreach($this->fields as $title => $type){
      $this->form_data['data'][$i]->validate($title, array('required', 'string', 'not_empty'));
      $i++;
    }
    
    return $this;
    
  }
  
  /**
   * Renders a form based on the provided fields.
   * Note: this outputs directly to the output stream.
   * @return $this Enables chaining.
   */
  public function render(&$id)
  {
    
    $fields = array(
      
      //Remove these fields
      'id' => false,
      'subject' => false,
      'recipient' => false,
      'mail_contents' => false
      
    );
    
    $i=0;
    foreach($this->fields as $title => $type)
    {
      
      $fields["data[$i]"] = array(
        'title' => $title,
        'type' => $type
      );
      
      $i++;
      
    }
    
    mk('Sql')->model('mail', 'FormEntries')
      ->render_form($id, '?rest=mail/form_entry/'.$this->form_id, array(
        'method' => 'POST',
        'fields' => $fields
      ));
    
    return $this;
    
  }
  
  /**
   * Creates HTML output for the submitted data.
   * @return string The HTML output.
   */
  public function to_html()
  {
    
    $html = '';
    
    $i=0;
    foreach($this->fields as $title => $type)
    {
      
      $value = $this->form_data['data'][$i];
      $html .= "<div><strong>$title:</strong></div>".n;
      $html .= "<div>$value</div>".br.n;
      $i++;
      
    }
    
    return $html;
    
  }
  
  /**
   * Cleans the session data when this form is completed.
   * @return $this Enables chaining.
   */
  public function remove()
  {
    
    if(mk('Data')->session->mail_forms->{$this->form_id}->is_set())
      mk('Data')->session->mail_forms->{$this->form_id}->un_set();
    
    return $this;
    
  }
  
  /**
   * Stores the form data in the current session.
   * @return void
   */
  protected function store()
  {
    
    //If needed, create a unique form ID.
    if(!isset($this->form_id))
    {
      
      do{
        $this->form_id = mk('Security')->random_string(20);
      }
      
      while(
        mk('Data')->session->mail_forms->{$this->form_id}->is_set()
      );
      
    }
    
    //Store data.
    mk('Data')->session->mail_forms->{$this->form_id}->merge(array(
      'subject' => $this->subject,
      'recipient' => $this->recipient,
      'fields' => $this->fields,
      'sender' => $this->sender
    ));
    
  }
  
}