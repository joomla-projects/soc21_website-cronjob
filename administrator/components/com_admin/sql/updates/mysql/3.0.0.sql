# Placeholder file for database changes for version 3.0.0
ALTER TABLE `#__contact_details` DROP `imagepos`;
ALTER TABLE `#__content` DROP COLUMN `title_alias`;
ALTER TABLE `#__content` DROP COLUMN `sectionid`;
ALTER TABLE `#__content` DROP COLUMN `mask`;
ALTER TABLE `#__content` DROP COLUMN `parentid`;
ALTER TABLE `#__newsfeeds` DROP COLUMN `filename`;
ALTER TABLE `#__weblinks` DROP COLUMN `sid`;
ALTER TABLE `#__weblinks` DROP COLUMN `date`;
ALTER TABLE `#__weblinks` DROP COLUMN `archived`;
ALTER TABLE `#__weblinks` DROP COLUMN `approved`;
ALTER TABLE `#__menu` DROP COLUMN `ordering`;
ALTER TABLE `#__weblinks` ADD COLUMN `version` int(10) unsigned NOT NULL DEFAULT '1';
ALTER TABLE `#__weblinks` ADD COLUMN `images` text NOT NULL;
ALTER TABLE `#__newsfeeds` ADD COLUMN `description` text NOT NULL;
ALTER TABLE `#__newsfeeds` ADD COLUMN `version` int(10) unsigned NOT NULL DEFAULT '1';
ALTER TABLE `#__newsfeeds` ADD COLUMN `hits` int(10) unsigned NOT NULL DEFAULT '0';
ALTER TABLE `#__newsfeeds` ADD COLUMN `images` text NOT NULL;
ALTER TABLE `#__contact_details` ADD COLUMN `version` int(10) unsigned NOT NULL DEFAULT '1';
ALTER TABLE `#__contact_details` ADD COLUMN `hits` int(10) unsigned NOT NULL DEFAULT '0';
ALTER TABLE `#__banners` ADD COLUMN `created_by` int(10) unsigned NOT NULL DEFAULT '0';
ALTER TABLE `#__banners` ADD COLUMN `created_by_alias` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `#__banners` ADD COLUMN `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00';
ALTER TABLE `#__banners` ADD COLUMN `modified_by` int(10) unsigned NOT NULL DEFAULT '0';
ALTER TABLE `#__banners` ADD COLUMN `version` int(10) unsigned NOT NULL DEFAULT '1';
ALTER TABLE `#__categories` ADD COLUMN `version` int(10) unsigned NOT NULL DEFAULT '1';