<?php
$staff_players_table = "gameCloud.staffPlayers";
$verifications_table = "gameCloud.licenseVerifications";
$license_management_table = "gameCloud.managedLicenses";
$account_purchases_table = "gameCloud.purchases";
$punished_players_table = "gameCloud.punishedPlayers";
$configuration_changes_table = "gameCloud.automaticConfigurationChanges";
$disabled_detections_table = "gameCloud.disabledDetections";
$server_specifications_table = "gameCloud.serverSpecifications";
$customer_support_table = "gameCloud.customerSupport";
$customer_support_commands_table = "gameCloud.customerSupportCommands";
$connection_count_table = "gameCloud.connectionCount";
$cross_server_information_table = "gameCloud.crossServerInformation";
$accepted_purposes_table = "gameCloud.acceptedPurposes";
$discord_webhooks_table = "gameCloud.discordWebhooks";
$failed_discord_webhooks_table = "gameCloud.failedDiscordWebhooks";
$accepted_platforms_table = "gameCloud.acceptedPlatforms";
$staff_announcements_table = "gameCloud.staffAnnouncements";
$detection_slots_table = "gameCloud.detectionSlots";
$detection_slots_tracking_table = "gameCloud.detectionSlotsTracking";
$ai_assistance_table = "gameCloud.aiAssistance";

// Separator

$ai_assistance_hash = 518924133;
$ai_instruction_ids = array(2);

// Separator

$customer_support_discord_webhook = "https://discord.com/api/webhooks/1165260211540467802/aNXb5HOp0vwsEGc-M7-TtQNxc2v1o9gzyTBlxy7Y8b68wdqw6QALXguNZS8AVuahXkzl";

$spartan_anticheat_check_names = array(
    // Exempted: Speed, MorePackets, IrregularMovements, NoFall
    "Criticals", "FastBow", "FastClicks", "Exploits", "ImpossibleInventory",
    "InventoryClicks", "ItemDrops", "IllegalPosition", "NoSlowdown", "Sprint",
    "AutoRespawn", "FastEat", "FastHeal", "NoSwing", "BlockReach", "GhostHand",
    "ImpossibleActions", "Liquids", "XRay", "MachineLearning", "CombatAnalysis",
    "Clip", "Fly", "ElytraMove", "BoatMove", "Nuker", "Jesus", "KillAura",
    "EntityMove", "Velocity", "FastPlace", "FastBreak", "IrregularMovements"
);
$ultimatestats_statistic_names = array(
    "Time Spent", "Health", "Food Level", "Inventory Items", "First Time Joined",
    "CPS", "Latency", "TPS", "RAM", "Players", "CPU", "Players Killed", "Mobs Killed",
    "Animals Killed", "Villagers Killed", "Deaths By Player", "Deaths By Mobs",
    "Deaths By Suicide", "Players Damaged", "Mobs Damaged", "Animals Damaged",
    "Villagers Damaged", "Hits Dealt", "Hits Missed", "Percentage", "Ores Mined",
    "Seeds Planted", "Blocks Placed", "Blocks Broken", "Blocks Ignited",
    "Fishing Successes", "Times Respawned", "Distance Travelled", "Worlds Visited",
    "Times Teleported", "Potions Made", "Items Crafted", "Foods Cooked",
    "Items Picked", "Items Dropped", "Chat Messages Sent", "Times Kicked",
    "Commands Executed", "Times Joined", "Times Left", "Time Played",
    "Foods Eaten", "Hearts Regained", "Hearts Lost", "Armors Worn", "Doors Interacted",
    "Chests Interacted", "Levers Interacted", "Fence Gates Interacted",
    "Buttons Interacted", "Trapdoors Interacted", "Droppers Interacted",
    "Dispensers Interacted", "Hoppers Interacted", "Furnaces Interacted",
    "Craft Tables Interacted", "Players Banned", "Players Muted", "Players Kicked",
    "Players Frozen", "Players Teleported", "Players Spectated", "Story",
    "Nether", "End", "Adventure", "Husbandry"
);