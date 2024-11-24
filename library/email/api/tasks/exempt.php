<?php
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/communication.php';
require_once '/var/www/.structure/library/email/init.php';
$token = get_form_get("token");

if (strlen($token) === EmailVariables::EXEMPT_TOKEN_LENGTH) {
    require_once '/var/www/.structure/library/memory/init.php';

    if (has_memory_limit("email-exempt-via-token", 10, "60 seconds")) {
        echo "<b>Please wait before doing this again</b>";
    } else {
        $query = get_sql_query(
            EmailVariables::USER_EXEMPTION_KEYS_TABLE,
            array("plan_id", "email_id"),
            array(
                array("token", $token),
                array("deletion_date", "NULL")
            ),
            null,
            1
        );

        if (!empty($query)) {
            $object = $query[0];
            private_file_get_contents("https://" . get_domain() . "/email/exempt/?planID={$object->plan_id}&email={$object->email_id}&reason=User Exemption");
            echo "<b>You have been unsubscribed from this email</b>";
        } else {
            echo "<b>Token not found</b>";
        }
    }
} else if (is_private_connection()) {
    $planID = get_form_get("planID");

    if (is_numeric($planID) && $planID > 0) {
        $reason = get_form_get("reason");
        $hasReason = false;

        if (strlen($reason) > 0) {
            $reason = properly_sql_encode($reason);
            $hasReason = true;
        }
        $email = get_form_get("email");

        if (is_numeric($email) && $email > 0) {
            $query = get_sql_query(
                EmailVariables::STORAGE_TABLE,
                array("id"),
                array(
                    array("id", $email),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = get_sql_query(
                    EmailVariables::EXEMPTIONS_TABLE,
                    array("id"),
                    array(
                        array("plan_id", $planID),
                        array("email_id", $email),
                        array("deletion_date", null)
                    ),
                    null,
                    1
                );

                if (empty($query)
                    && sql_insert(
                        EmailVariables::EXEMPTIONS_TABLE,
                        array(
                            "plan_id" => $planID,
                            "email_id" => $email,
                            "creation_date" => get_current_date(),
                            "creation_reason" => ($hasReason ? $reason : null)
                        ))) {
                    echo "true";
                } else {
                    echo "false";
                }
            } else {
                echo "false";
            }
        } else if (is_email($email)) {
            $query = get_sql_query(
                EmailVariables::STORAGE_TABLE,
                array("id"),
                array(
                    array("email_address", properly_sql_encode($email)),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = get_sql_query(
                    EmailVariables::EXEMPTIONS_TABLE,
                    array("id"),
                    array(
                        array("plan_id", $planID),
                        array("email_id", $query[0]->id),
                        array("deletion_date", null)
                    ),
                    null,
                    1
                );

                if (empty($query)
                    && sql_insert(
                        EmailVariables::EXEMPTIONS_TABLE,
                        array(
                            "plan_id" => $planID,
                            "email_id" => $email,
                            "creation_date" => get_current_date(),
                            "creation_reason" => ($hasReason ? $reason : null)
                        ))) {
                    echo "true";
                } else {
                    echo "false";
                }
            } else {
                echo "false";
            }
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
}