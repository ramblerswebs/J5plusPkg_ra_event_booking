DROP TABLE IF EXISTS `#__ra_event_bookings`;
-- Mail Templates UNINSTALL SQL for com_ra_eventbooking
-- Generated for Joomla 5
-- Place at: administrator/components/com_ra_eventbooking/sql/uninstall.mysql.sql

DELETE FROM `#__mail_templates`
WHERE `extension` = 'com_ra_eventbooking';