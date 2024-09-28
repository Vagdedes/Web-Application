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

    private const
        MOTIVATOR_PATREON_TIER = 4064030,
        SPONSOR_PATREON_TIER = 9784720,
        VISIONARY_PATREON_TIER = 21608146,
        DETECTION_SLOTS_50_TIER = 22808702,
        DETECTION_SLOTS_20_TIER = 22435075;

    public const DETECTION_SLOTS_UNLIMITED_TIER = array(
        23739985, // 8 months split
        23711252, // 6 months split
        23739993, // 4 months split
        23739990, // 3 months split
        23739997, // 2 months split
        23740028, // Pay once
        self::VISIONARY_PATREON_TIER,
        self::SPONSOR_PATREON_TIER,
        self::MOTIVATOR_PATREON_TIER,
        self::DETECTION_SLOTS_50_TIER,
        self::DETECTION_SLOTS_20_TIER
    );

    public const DETECTION_SLOTS_UNLIMITED_REQUIRED_EUR = 50;
}