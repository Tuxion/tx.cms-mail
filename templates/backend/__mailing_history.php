<?php namespace components\mail; if(!defined('TX')) die('No direct access.');?>

<ol class="history-wrapper">
  <li class="mailing">
    <a href="#" class="create-new"><span class="icon-plus"></span><?php __($names->component, 'New mailing') ?></a>
  </li>
  <?php foreach($data as $mailing): ?>
    <li class="mailing">
      <a href="#" data-id="<?php echo $mailing->id; ?>">
        <strong class="subject"><?php echo $mailing->subject; ?></strong>
        <span class="state"><?php echo $mailing->state_title; ?></span>
        <span class="dt-created"><?php echo $mailing->dt_created; ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ol>

<script type="text/javascript">
jQuery(function($){
  
  $('.history-wrapper .mailing a').on('click', function(e){
    
    e.preventDefault();
    
    $this = $(this);
    var url = "<?php echo url('?section=mail/mailing_editor',1); ?>";
    
    if($this.is(':not(.create-new)') && $this.attr('data-id')){
      url += "&options[mailing_id]="+$this.attr('data-id');
    }
    
    var hasFeedback = (window.app && app.Feedback);
    
    if(hasFeedback) app.Feedback.working("<?php __($names->component, 'Loading mailing'); ?>");
    
    $.ajax(url)
      .done(function(html){
        
        if(html && txBackend.mailing.editor){
          txBackend.mailing.editor.replaceWith(html);
          app.Feedback.success("<?php __($names->component, 'Mailing loaded'); ?>")
        }else{
          app.Feedback.error("<?php __($names->component, 'There was a problem loading the mailing editor'); ?>");
        }
        
      })
      .error(function(xhs, request, message){
        app.Feedback.error(message);
      });
    
  });
  
});
</script>
