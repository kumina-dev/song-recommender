<?php

ini_set('include_path', '.:/home/kumina/kumina.dev/prod/utils:/home/kumina/kumina.dev/prod/components');

require_once('config.php');

// Function for Spotify access token
function getAccessToken($clientId, $clientSecret) {
    $url = 'https://kumina.dev/api/spotify/accessToken.php';

    $data = json_encode(array(
        'clientId' => $clientId,
        'clientSecret' => $clientSecret
    ));

    $options = array(
        'http' => array(
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => $data
        )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result !== false) {
        $decoded = json_decode($result, true);
        if (isset($decoded['access_token'])) {
            return $decoded['access_token'];
        } else {
            return 'Error: Access token not found in response.' . $result;
        }
    } else {
        return 'Error: Failed to fetch access token.';
    }
}

// Get access token
$accessToken = getAccessToken($clientId, $clientSecret);

// Function to extract track ID from Spotify URL
function getTrackIdFromUrl($url) {
    // Match the track ID from a Spotify URL
    preg_match('/track\/([a-zA-Z0-9]+)/', $url, $matches);

    // Check if a match is found
    if (isset($matches[1])) {
        return $matches[1];
    } else {
        return false;
    }
}

// Function to get song recommendations
function getSongRecommendations($token, $seed) {
    $url = "https://api.spotify.com/v1/recommendations?seed_tracks=$seed";

    $options = [
        'http' => [
            'header' => "Authorization: Bearer $token",
            'method' => 'GET',
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Check for errors in API request
    if ($result === FALSE) {
        die('Error fetching recommendations from Spotify API: ' . error_get_last()['message']);
    }

    return $result;
}

$recommendedTracks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = isset($_POST['song_url']) ? $_POST['song_url'] : '';

    // Extract track ID from the Spotify URL
    $trackId = getTrackIdFromUrl($input);

    if ($trackId) {
        $accessToken = getAccessToken($clientId, $clientSecret);
        $songInfoUrl = "https://api.spotify.com/v1/tracks/$trackId";
        $options = [
            'http' => [
                'header' => "Authorization: Bearer $accessToken",
                'method' => 'GET',
            ],
        ];
        $context = stream_context_create($options);
        $songInfoResult = file_get_contents($songInfoUrl, false, $context);
        $songInfo = json_decode($songInfoResult);

        if ($songInfo === FALSE) {
            die('Error fetching song information from Spotify API: ' . error_get_last()['message']);
        }

        if ($songInfo && isset($songInfo->name) && isset($songInfo->artists)) {
            $songName = $songInfo->name;
            $artistNames = implode(', ', array_column($songInfo->artists, 'name'));
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo 'document.getElementById("song_name_container").innerHTML = "' . htmlspecialchars($songName) . ' by ' . htmlspecialchars($artistNames) . '";';
            echo '});';
            echo '</script>';
        } else {
            die('Error: Song information not found in response.');
        }
        
        $seedTrack = $trackId;
        $result = getSongRecommendations($accessToken, $seedTrack);

        // Attempt to decode the JSON response
        $recommendations = json_decode($result);

        // Check for JSON decoding errors
        if (json_last_error() != JSON_ERROR_NONE) {
            echo '<p>Error decoding JSON response from Spotify API: ' . json_last_error_msg() . '</p>';
            echo '<p>Raw JSON response: <pre>' . htmlspecialchars($result) . '</pre></p>';
            die();
        }

        if ($recommendations && isset($recommendations->tracks) && !empty($recommendations->tracks)) {
            foreach ($recommendations->tracks as $track) {
                if (isset($track->album->images[2])) {
                    $image = $track->album->images[2];
                    $artistNames = implode(', ', array_column($track->artists, 'name'));
        
                    $recommendedTracks .= <<<HTML
                        <li>
                            <div>
                                <img src="{$image->url}" alt="{$track->name}" width="{$image->width}" height="{$image->height}">
                            </div>
                            <div class="song-details">
                                <span>{$track->name}</span>
                                <span>{$artistNames}</span>
                            </div>
                            <div class="logo">
                                <a href="{$track->uri}" target="_blank">
                                    <i class="fa-brands fa-spotify"></i>
                                </a>
                            </div>
                        </li>
                    HTML;
                }
            }
        } else {
            echo '<p>';
            if ($recommendations === null) {
                echo 'Error fetching recommendations. Please try again.';
            } else {
                echo 'No recommendations found.';
            }
            echo '</p>';
        }
    } else {
        $recommendedTracks = '<p>Invalid Spotify URL. Please enter a valid track URL.</p>';
    }
} else {
    $recommendedTracks = '<p>Enter a song URL and click "Get Recommendations" to see song recommendations.</p>';
}
?>

<!DOCTYPE html>
<html lang="en">

<?php
$pageTitle = "Song Recommender";
include_once("head.php");
?>

<link rel="stylesheet" href="css/style.css?<?php echo time();?>">
<body class="bg-gray-900 text-white">
    <?php include_once("home/navbar.php"); ?>

    <main>
        <div class="container mx-auto mt-12 mb-24 md:mt-24 md:mb-48">
            <div class="grid gap-4 m-8 md:m-0 md:grid-cols-3">
                <!-- Second column -->
                <div class="order-1 md:order-none">
                    <!-- History -->
                    <div class="flex flex-col justify-between gap-2 rounded-lg border border-purple-800 p-4 md:flex-row md:items-center">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
                            <p class="text-purple-100">Search History</p>
                        </div>

                        <p class="w-fit rounded-md bg-purple-800 px-4 py-1">Soon&#8482;</p>
                    </div>
                </div>
            
                <!-- First column -->
                <div class="order-2 md:order-none md:col-span-2 md:col-start-1 md:row-start-1">
                    <div class="grid grid-cols-subgrid">
                        <div class="md:col-start-2">
                            <!-- Form -->
                            <div class="bg-gray-800 p-4 mb-4 rounded-lg">
                                <h1 class="text-xl font-bold mb-4 inline-block md:text-2xl">Song Recommender</h1>
                                <span> - <em class="text-sm inline-block">Public Demo</em></span>
                                <p class="text-gray-400 text-base italic -mt-2 mb-4">Powered by Spotify API</p>
                                
                                <form action="#" method="post" id="song_form">
                                    <input type="text" name="song_url" id="song_url" placeholder="Enter Song URL" class="block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                                    <button class="mt-4 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 focus:outline-none focus:bg-purple-700">Get Recommendations</button>
                                    <div id="song_name_container" class="mt-4"></div>
                                </form>
                            </div>
                            
                            <!-- Recommendations -->
                            <div class="bg-gray-800 p-4 rounded-lg relative">
                                <h2 class="text-lg font-medium mb-4 md:text-xl">Recommended Tracks</h2>
                                <?php echo $recommendedTracks; ?>
                                <button onclick="refreshRecommendations()" class="absolute top-0 right-0 mt-2 mr-2 text-gray-500 hover:text-gray-700">Refresh</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function refreshRecommendations() {
            location.reload();
        }
    </script>
</body>
</html>
