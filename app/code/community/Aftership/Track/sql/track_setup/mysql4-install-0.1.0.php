<?php
//echo "start";
$installer = $this;
$installer->startSetup();
$installer->run("DROP TABLE IF EXISTS `{$installer->getTable('track/track')}`;");
$installer->run("
CREATE TABLE `{$installer->getTable('track/track')}` (
  `track_id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_number` varchar(255) NOT NULL,
  `ship_comp_code` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telephone` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `posted` int(11) NOT NULL DEFAULT '0',
  `order_id` varchar(255) NOT NULL,
  PRIMARY KEY (`track_id`),
  KEY `posted` (`posted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


");
$installer->endSetup();


//echo 'Running This Upgrade: '.get_class($this)."\n <br /> \n";
//die("Exit for now");
/*
CREATE TABLE `{$installer->getTable('track/user')}` (
  `user_id` int(11) NOT NULL,
  `login_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `consumer_key` varchar(255) NOT NULL,
  `consumer_secret` varchar(255) NOT NULL,
  `status` int(11) NOT NULL,
  PRIMARY KEY (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/
?>