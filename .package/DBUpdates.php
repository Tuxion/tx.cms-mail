<?php namespace components\mail; if(!defined('TX')) die('No direct access.');

//Make sure we have the things we need for this class.
tx('Component')->check('update');
tx('Component')->load('update', 'classes\\BaseDBUpdates', false);

class DBUpdates extends \components\update\classes\BaseDBUpdates
{
  
  protected
    $component = 'mail',
    $updates = array(
      '1.1' => '2.0',
      '2.0' => '2.1'
    );
  
  public function update_to_2_1($current_version, $forced)
  {
    
    if($forced === true){
      tx('Sql')->query('DROP TABLE IF EXISTS `#__mail_subscriptions`');
    }
    
    tx('Sql')->query('
      CREATE TABLE `#__mail_subscriptions` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `key` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL,
        `name` varchar(255) NULL,
        `subscription` ENUM(\'UNKNOWN\', \'SUBSCRIBED\', \'UNSUBSCRIBED\') NOT NULL DEFAULT \'UNKNOWN\',
        `dt_subscribed` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `dt_unsubscribed` TIMESTAMP NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key` (`key`),
        UNIQUE KEY `email` (`email`),
        KEY `subscription` (`subscription`),
        KEY `dt_subscribed` (`dt_subscribed`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    
  }
  
  public function update_to_2_0($current_version, $forced)
  {
    $this->install_2_0(false, $forced);
  }
  
  public function install_2_0($dummydata, $forced)
  {
    
    if($forced === true){
      tx('Sql')->query('DROP TABLE IF EXISTS `#__mail_mailings`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__mail_mailing_recipients`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__mail_mailing_testers`');
      tx('Sql')->query('DROP TABLE IF EXISTS `#__mail_templates`');
    }
    
    tx('Sql')->query('
      CREATE TABLE `#__mail_mailings` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `state` ENUM(\'DRAFTING\', \'TESTING\', \'ERROR\', \'SENT\') NOT NULL DEFAULT \'DRAFTING\',
        `subject` varchar(255) NOT NULL,
        `message` LONGTEXT NOT NULL,
        `dt_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `dt_sent` TIMESTAMP NULL,
        PRIMARY KEY (`id`),
        KEY `state` (`state`),
        KEY `dt_created` (`dt_created`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__mail_mailing_recipients` (
        `mailing_id` int(10) unsigned NOT NULL,
        `type` ENUM(\'USER\', \'GROUP\') NOT NULL,
        `recipient_id` int(10) unsigned NOT NULL,
        PRIMARY KEY (`mailing_id`, `type`, `recipient_id`),
        KEY `mailing_id` (`mailing_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__mail_mailing_testers` (
        `mailing_id` int(10) unsigned NOT NULL,
        `type` ENUM(\'USER\', \'GROUP\') NOT NULL,
        `recipient_id` int(10) unsigned NOT NULL,
        PRIMARY KEY (`mailing_id`, `type`, `recipient_id`),
        KEY `mailing_id` (`mailing_id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    tx('Sql')->query('
      CREATE TABLE `#__mail_templates` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `subject` varchar(255) DEFAULT NULL,
        `message` LONGTEXT DEFAULT NULL,
        PRIMARY KEY (`id`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ');
    
    //Queue self-deployment with CMS component.
    $this->queue(array(
      'component' => 'cms',
      'min_version' => '1.2'
      ), function($version){
        
        //Ensures the mail component and mailing view.
        tx('Component')->helpers('cms')->_call('ensure_pagetypes', array(
          array(
            'name' => 'mail',
            'title' => 'Mailing component'
          ),
          array(
            'mailing' => true
          )
        ));
        
      }
    ); //END - Queue CMS 1.2+
    
  }
  
}

