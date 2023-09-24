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

// Separator

$spartan_anticheat_product_id = 1;
$spartan_anticheat_check_names = array(
    // Exempted: Speed, MorePackets, IrregularMovements, NoFall
    "Criticals", "FastBow", "FastClicks", "Exploits", "ImpossibleInventory",
    "InventoryClicks", "ItemDrops", "IllegalPosition", "NoSlowdown", "Sprint",
    "AutoRespawn", "FastEat", "FastHeal", "NoSwing", "BlockReach", "GhostHand",
    "ImpossibleActions", "Liquids", "XRay", "MachineLearning", "CombatAnalysis",
    "Clip", "Fly", "ElytraMove", "BoatMove", "Nuker", "Jesus", "KillAura",
    "EntityMove", "Velocity", "FastPlace", "FastBreak", "NormalMovements"
);
$spartan_anticheat_1_0_checks = array(
    "Speed" => array("horizontal: 0.1", "horizontal: 0.2", "horizontal: 0.3", "horizontal: 0.4", "horizontal: 0.5"), // Default
    "IrregularMovements" => array("box-difference 0.", "box-difference: 1."), // Default
    "FastPlace" => array("type: fast"), // Code
    "FastBreak" => array("seconds: 0.26", "seconds: 0.27", "seconds: 0.28", "seconds: 0.29", "seconds: 0.3", "seconds: 0.4", "seconds: 0.5", "seconds: 0.6", "seconds: 0.7", "seconds: 0.8", "seconds: 0.9", "seconds: 1.", "seconds: 2.", "seconds: 3.", "seconds: 4.", "seconds: 5."), // Default
    "BlockReach" => array("distance: 6.", "distance: 7.", "distance: 8.", "distance: 9."), // Default
    "Exploits" => array("type: undetected-movement"), // Default
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