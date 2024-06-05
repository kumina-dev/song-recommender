<?php

ini_set('include_path', '.:/home/kumina/kumina.dev/prod/utils:/home/kumina/kumina.dev/prod/components');

require_once('config.php');

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

define('API_URL', 'https://kumina.dev/api');
define('API_KEY', 'asXsG3G5');

// Function to get recommendations from API
function getRecommendations($apiKey, $songUrl) {
    $client = new Client();
    try {
        $response = $client->post(API_URL . '/recommendations', [
            'headers' => [
                'Authorization' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'song_url' => $songUrl,
            ],
        ]);

        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return ['error' => 'Error fetching recommendations: ' . $e->getMessage()];
    }
}

// Function to get recommendations from API using song name and artist name
function getRecommendationsSongName($apiKey, $songName, $artistName) {
    $client = new Client();
    try {
        $response = $client->post(API_URL, [
            'headers' => [
                'Authorization' => $apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'song_name' => $songName,
                'artist_name' => $artistName,
            ],
        ]);

        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return ['error' => 'Error fetching recommendations: ' . $e->getMessage()];
    }
}

function getTrackFromId($trackId) {
    $client = new Client();
    try {
        $response = $client->post(API_URL . '/song-info', [
            'headers' => [
                'Authorization' => API_KEY,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'track_id' => $trackId,
            ],
        ]);
        
        return json_decode($response->getBody(), true);
    } catch (Exception $e) {
        return ['error' => 'Error fetching track info: ' . $e->getMessage()];
    }
}

$recommendedTracks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiKey = API_KEY;
    $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : '';

    if ($inputType === 'song_url') {
        $songUrl = isset($_POST['song_url']) ? $_POST['song_url'] : '';
        $recommendedTracks = getRecommendations($apiKey, $songUrl);

        $trackId = isset($recommendedTracks['seeds'][0]['id']) ? $recommendedTracks['seeds'][0]['id'] : '';
        $trackInfo = getTrackFromId($trackId);
        
        if ($trackInfo && isset($trackInfo['name']) && isset($trackInfo['artists'])) {
            $songName = $trackInfo['name'];
            $artistNames = implode(', ', array_column($trackInfo['artists'], 'name'));
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo 'document.getElementById("song_name_container").innerHTML = "' . htmlspecialchars($songName) . ' by ' . htmlspecialchars($artistNames) . '";';
            echo '});';
            echo '</script>';
        } else {
            die('Error: Failed to get track info.');
        }
    } else if ($inputType === 'song_name_artist') {
        $songName = isset($_POST['song_name']) ? $_POST['song_name'] : '';
        $artistName = isset($_POST['artist_name']) ? $_POST['artist_name'] : '';
        $recommendedTracks = getRecommendationsSongName($apiKey, $songName, $artistName);

        if (!empty($songName) && !empty($artistName)) {
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo 'document.getElementById("song_name_container").innerHTML = "' . htmlspecialchars($songName) . ' by ' . htmlspecialchars($artistName) . '";';
            echo '});';
            echo '</script>';
        } else {
            die('Error: Invalid song name or artist name.');
        }
    } else {
        $recommendedTracks = ['error' => 'Invalid input type.'];
    }
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
                                    <select name="input_type" id="input_type" class="block w-full p-4 bg-transparent appearance-none text-gray-300 border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="song_url" class="text-gray-400" selected>Song URL</option>
                                        <option value="song_name_artist" class="text-gray-400">Song Name and Artist</option>
                                    </select>

                                    <input type="text" name="song_url" id="song_url" placeholder="Enter song URL" class="block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    
                                    <div class="hidden grid grid-cols-2 gap-4" id="song_name_artist">
                                        <input type="text" name="song_name" id="song_name" placeholder="Enter song name" class="block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <input type="text" name="artist_name" id="artist_name" placeholder="Enter artist name" class="block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </div>
                                    
                                    <button class="mt-4 bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 focus:outline-none focus:bg-purple-700">Get Recommendations</button>
                                    <div id="song_name_container" class="mt-4"></div>
                                </form>
                            </div>
                            
                            <!-- Recommendations -->
                            <div class="bg-gray-800 p-4 rounded-lg relative">
                                <h2 class="text-lg font-medium mb-4 md:text-xl">Recommended Tracks</h2>
                                <div class="recommended-tracks">
                                    <?php
                                    if (!empty($recommendedTracks)) {
                                        if (isset($recommendedTracks['error'])) {
                                            echo '<p>' . htmlspecialchars($recommendedTracks['error']) . '</p>';
                                        } else {
                                            foreach ($recommendedTracks['tracks'] as $track) {
                                                $image = $track['album']['images'][0]['url'];
                                                $trackName = htmlspecialchars($track['name'], ENT_QUOTES);
                                                 
                                                $artists = array_map(function($artist) {
                                                    return htmlspecialchars($artist['name'], ENT_QUOTES);
                                                }, $track['artists']);
                                                $artists = implode(', ', $artists);

                                                $uri = htmlspecialchars($track['uri'], ENT_QUOTES);
                                                $url = htmlspecialchars($track['external_urls']['spotify'], ENT_QUOTES);
    
                                                echo '<li>';
                                                echo '<div>';
                                                echo '<img src="' . $image . '" alt="' . $trackName . '" width="100" height="100">';
                                                echo '</div>';
                                                echo '<div class="song-details">';
                                                echo '<span>' . $trackName . '</span>';
                                                echo '<span>' . htmlspecialchars($artists) . '</span>';
                                                echo '</div>';
                                                echo '<div class="logo" id="logo">';
                                                echo '<a href="' . $url . '" onclick="openSpotify(\'' . $uri . '\', \'' . $url . '\')" target="_blank">';
                                                echo '<i class="fab fa-spotify"></i>';
                                                echo '</a>';
                                                echo '</div>';
                                                echo '</li>';
                                            }
                                        }
                                    } else {
                                        echo '<p>Enter a song and click "Get Recommendations" to get song recommendations.</p>';
                                    }
                                    ?>
                                </div>
                                <button id="refreshButton" onclick="refreshRecommendations()" class="hidden absolute top-0 right-0 mt-2 mr-2 text-gray-500 hover:text-gray-700">Refresh</button>
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

        document.addEventListener("DOMContentLoaded", function() {
            <?php
            if (isset($recommendedTracks['tracks']) && !empty($recommendedTracks['tracks'])) {
                echo 'document.getElementById("refreshButton").classList.remove("hidden");';
            }
            ?>
        });

        function openSpotify(uri, url) {
            var uriWindow = window.open(uri, '_blank');

            if (uriWindow) {
                uriWindow.focus();
            } else {
                setTimeout(function() {
                    window.open(url, '_blank');
                }, 1000);
            }
        }

        document.getElementById('input_type').addEventListener('change', function() {
            var selectedValue = this.value;
            document.getElementById('song_url').classList.add('hidden');
            document.getElementById('song_name_artist').classList.add('hidden');
            document.getElementById(selectedValue).classList.remove('hidden');
        });
    </script>
</body>
</html>
