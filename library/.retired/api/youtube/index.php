<?php
if (false) {
    $id = get_form_get("id");
    $key = "AIzaSyDdLHZ6key54Jx-MtMihikZukiKjkpmur0";
    $url = "https://www.googleapis.com/youtube/v3/videos?id=" . $id . "&key=$key&part=";

    if (strlen($id) > 0) {
        $data = json_decode(@file_get_contents($url . "snippet"));
        echo "Channel: " . $data->items[0]->snippet->channelTitle . "\r\n";
        echo "Title: " . $data->items[0]->snippet->title . "\r\n";
        echo "Description: " . $data->items[0]->snippet->description . "\r\n";
        echo "Image: " . $data->items[0]->snippet->thumbnails->standard->url . "\r\n";
        echo "Tags: " . implode(", ", $data->items[0]->snippet->tags) . "\r\n";

        $data = json_decode(@file_get_contents($url . "statistics"));
        echo "Views: " . $data->items[0]->statistics->viewCount . "\r\n";
        echo "Likes: " . $data->items[0]->statistics->likeCount . "\r\n";
        echo "Dislikes: " . $data->items[0]->statistics->dislikeCount . "\r\n";
        echo "Comments: " . $data->items[0]->statistics->commentCount . "\r\n";
    }
}