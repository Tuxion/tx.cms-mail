<?php namespace components\mail; if(!defined('TX')) die('No direct access.');?>

<h1><?php echo sprintf(__($names->component, 'Unsubscribing from %s', true), tx('Site')->title); ?></h1>

<?php if($data->is_unsubscribed()): ?>
  
  <p class="unsubscribe already-unsubscribed">
    <?php echo sprintf(__($names->component, 'The address \'%s\' is unsubscribed from the %s mailing', true), $data->email->get(), tx('Site')->title); ?>.
  </p>
  
  <p class="still-recieving already-unsubscribed">
    <?php __($names->component, 'If you are still receiving e-mails, please contact the webmaster'); ?>:
    <a class="webmaster" href="mailto:<?php echo (EMAIL_NAME_WEBMASTER.' <'.EMAIL_ADDRESS_WEBMASTER.'>'); ?>"><?php echo EMAIL_NAME_WEBMASTER; ?></a>
  </p>
  
<?php else: ?>
  
  <p class="unsubscribe confirm">
    <?php echo sprintf(__($names->component, 'Do you really want to unsubscribe \'%s\' from the %s mailing', true), $data->email->get(), tx('Site')->title); ?>?
  </p>
  
  <form class="form unsubscribe-form" method="post" action="<?php echo url('?action=mail/unsubscribe/post', true); ?>">
    <input type="hidden" name="key" value="<?php echo $data->key; ?>" />
    <div class="buttonHolder">
      <input class="primaryAction button grey" type="submit" name="cancel" value="<?php __('Cancel'); ?>" tabindex="1" />
      <input class="button black" type="submit" name="confirm" value="<?php __($names->component, 'Unsubscribe'); ?>" tabindex="2" />
    </div>
  </form>
  
<?php endif; ?>
