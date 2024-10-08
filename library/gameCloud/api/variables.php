<?php
$license_management_table = "gameCloud.managedLicenses";
$punished_players_table = "gameCloud.punishedPlayers";
$configuration_changes_table = "gameCloud.automaticConfigurationChanges";
$disabled_detections_table = "gameCloud.disabledDetections";
$connection_count_table = "gameCloud.connectionCount";
$accepted_purposes_table = "gameCloud.acceptedPurposes";
$failed_discord_webhooks_table = "gameCloud.failedDiscordWebhooks";
$accepted_platforms_table = "gameCloud.acceptedPlatforms";
$staff_announcements_table = "gameCloud.staffAnnouncements";
$detection_slots_table = "gameCloud.detectionSlots";
$detection_slots_tracking_table = "gameCloud.detectionSlotsTracking";

class GameCloudVariables
{
    public const
        SPARTAN_SYN = 7,
        DETECTION_SLOTS_UNLIMITED_PRODUCT = 26;
}