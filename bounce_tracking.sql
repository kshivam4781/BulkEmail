CREATE TABLE IF NOT EXISTS `bounce_logs` (
  `bounce_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `email_log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `bounce_type` enum('hard','soft') NOT NULL,
  `bounce_reason` text,
  `bounce_code` varchar(50),
  `bounce_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bounce_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `email_log_id` (`email_log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_bounce_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`campaign_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bounce_email_log` FOREIGN KEY (`email_log_id`) REFERENCES `email_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bounce_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 