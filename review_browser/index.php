<!DOCTYPE html>
<html>
<head>
    <title>Steam Reviews</title>
    <style>
        .reviews-container a div {
            width: fit-content;
        }
        .reviews-container a {
            text-decoration: none;
            width: fit-content;
            display: block;
        }
        .reviews-container a .review {
            background-color: #f0f0f0;
            padding: 5px;
            margin: 5px;
            border-radius: 5px;
            color: #000;
        }   
        .reviews-container a:hover .review {
            background-color: #fafafa;
            cursor: pointer;
        }
        .reviews-container a .review-text {
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
            color: #000;
        }
        .reviews-container a .reviewer {
            margin: 0;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Steam Reviews</h1>

<?php

$APPID = "3120040";

$API_KEY = file_get_contents('.steam_web_api_key.txt');

$url = "https://store.steampowered.com/appreviews/{$APPID}?json=1&filter=recent&language=all&purchase_type=all";

$caught_up = false;
$last_recommentation_id = file_exists('last_rec_id.txt') ? (int)file_get_contents('last_rec_id.txt') : 0;
$next_cursor = "*";
$new_reviews_reverse_order = [];

$limit = 20;

while (!$caught_up && $limit > 0) {
    $limit--;
    $ch = curl_init();
    if ($next_cursor == "*") {
        curl_setopt($ch, CURLOPT_URL, $url . "&num_per_page=1&cursor=*");
    } else {
        echo "<a href=" . $url . "&num_per_page=20&cursor=" . urlencode($next_cursor) . ">";
        echo "reading cursor...";
        echo "</a><br/><br/>";
        curl_setopt($ch, CURLOPT_URL, $url . "&num_per_page=20&cursor=" . urlencode($next_cursor));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data['query_summary']['num_reviews'] == 0) {
        $caught_up = true;
        break;
    }

    $reviews = $data['reviews'];
    $next_cursor = $data['cursor'];
    foreach ($reviews as $review) {
        $recommendation_id = $review['recommendationid'];
        if ($recommendation_id > $last_recommentation_id) {
            $new_reviews_reverse_order[] = $review;
        } else {
            $next_cursor = '';
            $caught_up = true;
        }
    }
}

$csv_file = 'all_reviews.csv';

if ($new_reviews_reverse_order) {
    file_put_contents('last_rec_id.txt', $new_reviews_reverse_order[0]['recommendationid']);

    $file_exists = file_exists($csv_file);
    
    $fp = fopen($csv_file, 'a');
    
    // Add header if file is new
    if (!$file_exists) {
        fputcsv($fp, ['recommendationid', 'short_review', 'player_name', 'review_url']);
    }

    $new_reviews = $new_reviews_reverse_order;
    usort($new_reviews, function($a, $b) {
        return $a['recommendationid'] - $b['recommendationid'];
    });
    // foreach ($new_reviews as $review) {
    //     echo $review['review'];
    //     echo "<br><br>";
    // }
    
    // Append new reviews
    foreach ($new_reviews as $review) {

        $steamid = $review['author']['steamid'];
        $steam_api_url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$API_KEY}&steamids={$steamid}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $steam_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $steam_response = curl_exec($ch);
        curl_close($ch);
        
        $steam_data = json_decode($steam_response, true);
        $player_info = $steam_data['response']['players'][0] ?? null;
        if ($player_info) {
            $player_name = $player_info['personaname'];
            $player_profile_url = $player_info['profileurl'];
            $review_url = $player_profile_url . "recommended/{$APPID}";
        } else {
            $player_name = "Unknown";
            $review_url = "";
        }
        
        fputcsv($fp, [
            $review['recommendationid'],
            (strlen($review['review']) > 100 ? substr($review['review'], 0, 100) . "..." : $review['review']),
            $player_name,
            $review_url
        ]);
    }
    
    fclose($fp);
} else {
    // pass, nothing to see here folks
}

// Read and display reviews from CSV file
$fp = fopen($csv_file, 'r');
if ($fp) {
    // Skip header row
    fgetcsv($fp);
    
    echo "<div class='reviews-container'>";
    
    while (($row = fgetcsv($fp)) !== false) {
        $short_review = $row[1];
        $player_name = $row[2];
        $review_url = $row[3];
        
        echo "<a href=" . $review_url . ">";
        echo "<div class='review'>";
        echo "<span class='reviewer'>" . htmlspecialchars($player_name) . ": </span>";
        echo "<span class='review-text'>" . htmlspecialchars($short_review) . "</span>";
        echo "</div>";
        echo "</a>";
    }
    fclose($fp);
    
    echo "</div>";
    echo "<br/>";
    echo "<br/>";
}

?>

</body>
</html>
