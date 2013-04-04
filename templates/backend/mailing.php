<?php namespace components\mail; if(!defined('TX')) die('No direct access.');?>

<script type="text/javascript">

//Sets the txBackend.mailing namespace and inits the editor default.
var txBackend = (window.txBackend || {});
txBackend['mailing'] = (txBackend.mailing || {editor:null});
window.txBackend = txBackend;

</script>

<h1><?php __($names->component, 'Mailing'); ?></h1>

<div class="clearfix">
  
  <div class="mailing-history">
    <?php echo $mailing->history; ?>
  </div>
  
  <div class="mailing-editor">
    <?php echo $mailing->editor; ?>
  </div>
  
</div>
