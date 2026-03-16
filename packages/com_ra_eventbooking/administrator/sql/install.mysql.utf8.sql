CREATE TABLE IF NOT EXISTS `#__ra_event_bookings` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,

`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT(11)  NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`modified_by` INT(11)  NULL  DEFAULT 0,
`event_id` VARCHAR(255) NOT NULL ,
`booking_data` LONGTEXT NULL ,
`waiting_data` LONGTEXT NULL ,
`event_data` LONGTEXT NULL ,
`created_by` INT(11) NULL DEFAULT 0,
`creation_date` DATETIME NULL DEFAULT NULL ,
`params` text NOT NULL,
PRIMARY KEY (`id`)
,KEY `idx_state` (`state`)
,KEY `idx_checked_out` (`checked_out`)
,KEY `idx_modified_by` (`modified_by`)
,KEY `idx_created_by` (`created_by`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;



INSERT INTO `#__content_types` (`type_title`, `type_alias`, `table`, `rules`, `field_mappings`, `content_history_options`)
SELECT * FROM ( SELECT 'Event setting','com_ra_eventbooking.eventsetting','{"special":{"dbtable":"#__ra_event_bookings","key":"id","type":"EventsettingTable","prefix":"Joomla\\\\Component\\\\Ra_eventbooking\\\\Administrator\\\\Table\\\\"}}', CASE 
                                    WHEN 'rules' is null THEN ''
                                    ELSE ''
                                    END as rules, CASE 
                                    WHEN 'field_mappings' is null THEN ''
                                    ELSE ''
                                    END as field_mappings, '{"formFile":"administrator\/components\/com_ra_eventbooking\/forms\/eventsetting.xml", "hideFields":["checked_out","checked_out_time","params","language" ,"payment_details"], "ignoreChanges":["modified_by", "modified", "checked_out", "checked_out_time"], "convertToInt":["publish_up", "publish_down"], "displayLookup":[{"sourceColumn":"catid","targetTable":"#__categories","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"group_id","targetTable":"#__usergroups","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"created_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"},{"sourceColumn":"access","targetTable":"#__viewlevels","targetColumn":"id","displayColumn":"title"},{"sourceColumn":"modified_by","targetTable":"#__users","targetColumn":"id","displayColumn":"name"}]}') AS tmp
WHERE NOT EXISTS (
	SELECT type_alias FROM `#__content_types` WHERE (`type_alias` = 'com_ra_eventbooking.eventsetting')
) LIMIT 1;
