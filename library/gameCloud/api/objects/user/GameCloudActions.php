<?php

class GameCloudActions
{
    private GameCloudUser $user;

    public function __construct(GameCloudUser $user)
    {
        $this->user = $user;
    }

    public function addStaffAnnouncement(int|string|null  $priority,
                                         int|string|null  $minimumVersion, int|string|null $maximumVersion,
                                         int|string|null  $cooldown, int|string|null $duration,
                                         int|string|float $announcement,
                                         bool             $checkValidity = true,
                                         bool             $avoidRedundantAnnouncements = true): bool
    {
        if (!$checkValidity || $this->user->isValid()) {
            if ((
                    !$avoidRedundantAnnouncements
                    || empty(get_sql_query(
                        $avoidRedundantAnnouncements,
                        array("id"),
                        array(
                            array("announcement", $announcement),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date())
                        ),
                        null,
                        1
                    ))
                ) && sql_insert(
                    GameCloudVariables::STAFF_ANNOUNCEMENTS_TABLE,
                    array(
                        "license_id" => $this->user->getLicense(),
                        "platform_id" => $this->user->getPlatform(),
                        "priority" => $priority,
                        "minimum_version" => $minimumVersion,
                        "maximum_version" => $maximumVersion,
                        "cooldown" => $cooldown,
                        "announcement" => $announcement,
                        "creation_date" => get_current_date(),
                        "expiration_date" => ($duration !== null ? get_future_date($duration) : null)
                    )
                )) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

}
