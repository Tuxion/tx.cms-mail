<?php namespace components\mail; if(!defined('TX')) die('No direct access.');
$uid = tx('Security')->random_string(20);
?>
<form method="put" id="<?php echo $uid; ?>" action="<?php echo url('rest=mail/mailing',1); ?>" class="form mailing-editor-form">
  
  <input type="hidden" name="id" value="<?php echo $data->mailing->id; ?>" />
  
  <div class="ctrlHolder">
    <label for="l_template_input"><?php __($names->component, 'Used template'); ?></label>
    <strong id="l_template_input"><?php echo $data->template->title->otherwise(__($names->component, 'NO_TEMPLATE', true)) ?></strong>
  </div>
  
  <div class="ctrlHolder">
    <label for="l_recipients_input"><?php __($names->component, 'Recipient(s)'); ?></label>
    <input class="big large no-enter recipients-autocomplete" data-recipient-class="recipient"
      type="text" id="l_recipients_input" name="recipients_input" tabindex="1" />
    <div class="recipients-container clearfix" data-recipient-class="recipient"></div>
  </div>
  
  <div class="ctrlHolder">
    <label for="l_testers_input"><?php __($names->component, 'Tester(s)'); ?></label>
    <input class="big large no-enter recipients-autocomplete" data-recipient-class="tester"
      type="text" id="l_testers_input" name="testers_input" tabindex="2" />
    <div class="recipients-container clearfix" data-recipient-class="tester"></div>
  </div>
  
  <div class="ctrlHolder">
    <label for="l_subject" accesskey="s"><?php __('Subject'); ?></label>
    <input class="big large no-enter" type="text" id="l_subject" name="subject"
      value="<?php echo $data->mailing->subject->otherwise($data->template->subject); ?>" tabindex="3" required />
  </div>
  
  <div class="ctrlHolder">
    <label for="<?php echo $uid; ?>-message" accesskey="m"><?php __('Message'); ?></label>
    <textarea id="<?php echo $uid; ?>-message" name="message" class="editor"
        tabindex="4"><?php echo $data->mailing->message->otherwise($data->template->message); ?></textarea>
  </div>
  
  <div class="buttonHolder">
    <input class="button grey" type="submit" name="DRAFT" value="<?php __($names->component, 'Save draft'); ?>" tabindex="7" />
    <input class="primaryAction button grey" type="submit" name="TEST" value="<?php __($names->component, 'Send test-mailing'); ?>" tabindex="5" />
    <input class="button black" type="submit" name="SEND" value="<?php __($names->component, 'Send mailing'); ?>" tabindex="6" />
  </div>
  
  <script type="text/javascript">
  $(function(){
    
    var $form = $('#<?php echo $uid; ?>');
    window.txBackend.mailing.editor = $form;
    
    var hasFeedback = (window.app && app.Feedback);
    
    // Init editor
    tx_editor.init({selector:"#<?php echo $uid; ?>-message"});
    
    //Prevent submitting with enter.
    $('.no-enter').on('keypress', function(e){ if(e.which == 13) e.preventDefault(); });
    
    //Make awesomesauce notifications possible.
    $form.restForm({
      
      beforeSubmit: function(data){
        
        data.command =  data.DRAFT ? 'DRAFT':
                        data.TEST ? 'TEST':
                        data.SEND ? 'SEND':
                        null;
        delete data.DRAFT;
        delete data.TEST;
        delete data.SEND;
        
        if(hasFeedback){
          switch(data.command){
            case 'DRAFT':
              app.Feedback.working("<?php __($names->component, 'Saving draft'); ?>");
              break;
            case 'TEST':
              app.Feedback.working("<?php __($names->component, 'Sending test-mailing'); ?>");
              break;
            case 'SEND':
              app.Feedback.working("<?php __($names->component, 'Sending mailing'); ?>");
              break;
          }
        }
        
      },
      
      success: function(mailing){
        
        //Ensure the ID is set.
        $form.find(':input[name=id]').val(mailing.id);
        
        if(!hasFeedback){
          $.flash('success', "<?php __($names->component, 'Sent mail successfully'); ?>");
        }
      },
      
      error: function(xhr, state, message){
        if(!hasFeedback){
          $.flash('error', message);
        }
      }
      
    });
    
    //Structured data for selected recipients.
    //selectedRecipients[<class>][<type>][<id>] = <boolean>
    var selectedRecipients = {
      recipient: {groups:{}, users:{}},
      tester: {groups:{}, users:{}}
    };
    
    //Pre-fetch the containers so we don't have to search the DOM all the time.
    var $containers = {
      recipient: $form.find('.recipients-container[data-recipient-class=recipient]'),
      tester: $form.find('.recipients-container[data-recipient-class=tester]')
    };
    
    var insertRecipient = function(recipientClass, item){
      
      var type = null;
      
      if(item.is_user){
        type = 'user';
      }
      
      else if(item.is_group){
        type = 'group';
      }
      
      else{
        window.console && console.log && console.log('Unsupported recipient type, use a "user" or "group" type.');
        return;
      }
      
      selectedRecipients[recipientClass][type+'s'][item.id] = true;
      
      $containers[recipientClass].prepend(
        $('<div>', {text: item.label, "class":type+' recipient'})
          .append('<input type="hidden" name="'+recipientClass+'_'+type+'s[]" value="'+item.id+'"><a href="#" class="remove">x</a>')
      );
      
    };
    
    $form.find('.recipients-autocomplete').each(function(){
      
      var $input = $(this)
        , recipientClass = $input.attr('data-recipient-class')
        , $container = $form.find('.recipients-container[data-recipient-class='+recipientClass+']')
        , selected = selectedRecipients[recipientClass]
      ;
      
      //Enable autocomplete plugin.
      $input.autocomplete({
        
        //Get options from remote and filter out already used ones locally.
        source: function(request, response){
          $.ajax('?rest=account/mail_autocomplete/'+request.term).done(function(results){
            
            //Convert object to array and filter out already selected ones.
            var options = [];
            $.each(results, function(index, item){
              if(!(item.is_user && selected.users[item.id]) && !(item.is_group && selected.groups[item.id]))
                options.push(item);
            });
            response(options);
            
          })
        },
        
        //Add the recipient to the list and store it in javascript vars too.
        select: function(e, ui){
          e.preventDefault();
          insertRecipient(recipientClass, ui.item);
          e.target.value = '';
        }
        
      }); //End - $.fn.autocomplete()
      
      //Allow removing of recipients that were added.
      $containers[recipientClass].on('click', '.remove', function(e){
        
        e.preventDefault();
        var div = $(e.target).closest('div');
        
        //If this is a user delete it from tester_users.
        if(div.is('.user'))
          delete selected.users[div.find('input').val()];
        
        //If this is a group delete it from tester_groups.
        else if (div.is('.group'))
          delete selected.groups[div.find('input').val()];
        
        div.remove();
        $input.focus();
        
      });
      
    }); //End - each(.recipients-autocomplete)
    
    <?php foreach($data->mailing->recipients as $recipient): ?>
      
      //Insert from server.
      insertRecipient('recipient', {
        id: '<?php echo $recipient->recipient_id; ?>',
        label: '<?php echo $recipient->label; ?>',
        is_user: <?php echo $recipient->type->get('string') === 'USER' ? 'true' : 'false'; ?>,
        is_group: <?php echo $recipient->type->get('string') === 'GROUP' ? 'true' : 'false'; ?>
      });
      
    <?php endforeach; ?>
    <?php foreach($data->mailing->testers as $tester): ?>
      
      //Insert from server.
      insertRecipient('tester', {
        id: '<?php echo $tester->recipient_id; ?>',
        label: '<?php echo $tester->label; ?>',
        is_user: <?php echo $tester->type->get('string') === 'USER' ? 'true' : 'false'; ?>,
        is_group: <?php echo $tester->type->get('string') === 'GROUP' ? 'true' : 'false'; ?>
      });
      
    <?php endforeach; ?>
    
  });
  </script>
  
</form>
