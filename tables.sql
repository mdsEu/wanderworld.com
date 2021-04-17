CREATE TABLE `friends` (
  `user_id` bigint unsigned NOT NULL,
  `friend_id` bigint unsigned NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '1=Pending,2=active,3=blocked',
  PRIMARY KEY (`user_id`,`friend_id`),
  KEY `fk_friends_friend` (`friend_id`),
  CONSTRAINT `fk_friends_friend` FOREIGN KEY (`friend_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_friends_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_comments_user` (`user_id`),
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)


CREATE TABLE `logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_logs_user` (`user_id`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
)


CREATE TABLE `invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `invited_id` bigint unsigned DEFAULT NULL,
  `invited_email` varchar(255) DEFAULT NULL,
  `invited_phone` varchar(255) DEFAULT NULL,
  `invited_fbid` varchar(255) DEFAULT NULL COMMENT 'Facebook user id',
  `invited_info` text DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '1=Pending,2=Accepted,3=Rejected,4=Created',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_invitations_user` (`user_id`),
  KEY `fk_invitations_invited` (`invited_id`),
  CONSTRAINT `fk_invitations_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_invitations_invited` FOREIGN KEY (`invited_id`) REFERENCES `app_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
)



CREATE TABLE `travels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `host_id` bigint unsigned DEFAULT NULL,
  `start_at` date DEFAULT NULL,
  `end_at` date DEFAULT NULL,
  `request_type` char(2) DEFAULT NULL COMMENT 'H=Host,HG=Host and Guider,G=Guider',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '1=Pending,2=Accepted,3=Rejected,4=Cancelled,5=Finished',
  `info` text NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_trequests_host` FOREIGN KEY (`host_id`) REFERENCES `app_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trequests_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `travel_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `travel_id` bigint unsigned NOT NULL,
  `contact_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `place_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_travels_contact` FOREIGN KEY (`contact_id`) REFERENCES `app_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_travels_travel` FOREIGN KEY (`travel_id`) REFERENCES `travels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)


CREATE TABLE `albums` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `travel_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '1=Accepted,2=Reported,3=Blocked',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_album_travel` FOREIGN KEY (`travel_id`) REFERENCES `travels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `album_id` bigint unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `disk` varchar(10) DEFAULT NULL,
  `times_report` int(200) unsigned NOT NULL DEFAULT 0,
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '1=Accepted,2=Reported,3=Blocked',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_photos_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)


CREATE TABLE `image_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned,
  `model_id` bigint unsigned,
  `reference` char(1) NOT NULL DEFAULT 'n' COMMENT 'u=AppUser,p=Photo,n=None',
  `comment` varchar(100) NOT NULL DEFAULT "",
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `uq_photo_report` UNIQUE (`user_id`, `model_id`, `reference`),
  CONSTRAINT `fk_photo_report_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned,
  `invited_id` bigint unsigned,
  `travel_id` bigint unsigned,
  `message` varchar(100) NULL DEFAULT NULL,
  `seen` tinyint NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_recommendations_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recommendations_invited` FOREIGN KEY (`invited_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recommendations_travel` FOREIGN KEY (`travel_id`) REFERENCES `travels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)



CREATE TABLE `friends_status` (
  `user_id` bigint unsigned,
  `friend_id` bigint unsigned,
  `status` char(1) not null default 1 COMMENT '1=Pending,2=active,3=blocked',
  PRIMARY KEY (`user_id`, `friend_id`),
  CONSTRAINT `fk_friends_status_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_friends_status_friend` FOREIGN KEY (`friend_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(150) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)

CREATE TABLE `app_user_interests` (
  `user_id` bigint unsigned,
  `interest_id` bigint unsigned,
  PRIMARY KEY (`user_id`, `interest_id`),
  CONSTRAINT `fk_app_user_interests_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_app_user_interests_interests` FOREIGN KEY (`interest_id`) REFERENCES `interests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)

CREATE TABLE `interests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)

CREATE TABLE `app_user_languages` (
  `user_id` bigint unsigned,
  `language_id` bigint unsigned,
  PRIMARY KEY (`user_id`, `language_id`),
  CONSTRAINT `fk_app_user_languages_user` FOREIGN KEY (`user_id`) REFERENCES `app_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)


CREATE TABLE `chat_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `disk` varchar(10) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
)
