CREATE TABLE IF NOT EXISTS `email_tracking` (
  `TrackingID` int(11) NOT NULL AUTO_INCREMENT,
  `CampaignID` int(11) NOT NULL,
  `RecipientEmail` varchar(255) NOT NULL,
  `FirstOpenedAt` datetime NOT NULL,
  `LastOpenedAt` datetime NOT NULL,
  `OpenCount` int(11) NOT NULL DEFAULT 1,
  `UserAgent` varchar(255) DEFAULT NULL,
  `IPAddress` varchar(45) DEFAULT NULL,
  `EmailClient` varchar(50) DEFAULT 'Unknown',
  PRIMARY KEY (`TrackingID`),
  KEY `CampaignID` (`CampaignID`),
  KEY `RecipientEmail` (`RecipientEmail`),
  CONSTRAINT `email_tracking_ibfk_1` FOREIGN KEY (`CampaignID`) REFERENCES `campaigns` (`campaign_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 