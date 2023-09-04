<?php

function loadHistory(Account $account, $isLoggedIn)
{
    if (!$isLoggedIn) {
        redirect_to_account_page(null, false, null);
    } else {
        echo "<div class='area'>
                    <div class='area_title'>Recent History</div>";
        $history = $account->getHistory()->get(
            array("action_id", "date"),
            50
        );

        if ($history->isPositiveOutcome()) {
            $history = $history->getObject();

            if (!empty($history)) {
                echo "<div class='area_text'>Our Dates & Time are available in the GMT+1 Timezone</div>";
                echo "<div class='area_board'><ul>
                    <li class='label'>
                        <div class='main'>Action</div><div>Date</div><div>Time</div>
                    </li>";

                foreach ($history as $row) {
                    $action = str_replace("_", "-", $row->action_id);
                    $fullDate = $row->date;
                    $date = substr($fullDate, 0, 10);
                    $time = substr($fullDate, 10, -3);
                    echo "<li><div class='main'>$action</div><div>$date</div><div>$time</div></li>";
                }
                echo "</ul></div>";
            } else {
                echo "<div class='area_text'>You don't have any recent history.</div>";
            }
        } else {
            echo "<div class='area_text'>" . $history->getMessage() . "</div>";
        }
        echo "</div>";
    }
}
