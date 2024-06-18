<?php

ini_set('include_path', '.:/home/kumina/kumina.dev/utils:/home/kumina/kumina.dev/components');

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
<body class="bg-gray-900 text-gray-300">
    <?php include_once("navbar.php"); ?>

    <main>
        <div class="container mx-auto mt-12 mb-24 md:mt-24 md:mb-48">
            <div class="grid gap-8 m-8 md:m-0 md:grid-cols-3">
                <!-- History -->
                <div class="order-1 md:order-none">
                    <div class="flex flex-col justify-between gap-4 rounded-lg border border-purple-800 p-6 md:flex-row md:items-center bg-gray-800 shadow-lg">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
                            <p class="text-purple-100 text-lg font-semibold">Search History</p>
                        </div>
                        <p class="w-fit rounded-md bg-purple-800 px-4 py-2 soon-animation text-white font-medium">Soon&#8482;</p>
                    </div>
                </div>

                <!-- Form and Recommendations -->
                <div class="order-2 md:order-none md:col-span-2">
                    <div class="bg-gray-800 p-8 rounded-lg shadow-lg">
                        <!-- Form -->
                        <div class="mb-8">
                            <h1 class="text-3xl font-bold mb-4">Song Recommender <span class="text-sm text-gray-400">- Public Demo</span></h1>
                            <p class="text-gray-400 italic mb-6">Powered by Spotify API</p>
                            <form action="#" method="post" id="song_form">
                                <div class="mb-6">
                                    <label for="input_type" class="block mb-2 text-sm font-medium text-gray-400">Input Type</label>
                                    
                                    <select name="input_type" id="input_type" class="block w-full p-4 bg-gray-700 border border-gray-600 rounded-md text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="song_url" selected>Song URL</option>
                                        <option value="song_name_artist">Song Name and Artist</option>
                                    </select>
                                </div>
                                
                                <div id="song_url_input" class="mb-6">
                                    <label for="song_url" class="block mb-2 text-sm font-medium text-gray-400">Song URL</label>
                                    <input type="text" name="song_url" id="song_url" placeholder="Enter song URL" class="block w-full p-4 bg-gray-700 border border-gray-600 rounded-md text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                
                                <div id="song_name_artist_input" class="hidden mb-6">
                                    <label for="song_name" class="block mb-2 text-sm font-medium text-gray-400">Song Name</label>
                                    <input type="text" name="song_name" id="song_name" placeholder="Enter song name" class="block w-full p-4 bg-gray-700 border border-gray-600 rounded-md text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 mb-4">
                                    <label for="artist_name" class="block mb-2 text-sm font-medium text-gray-400">Artist Name</label>
                                    <input type="text" name="artist_name" id="artist_name" placeholder="Enter artist name" class="block w-full p-4 bg-gray-700 border border-gray-600 rounded-md text-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                                
                                <button type="submit" class="w-full py-4 bg-purple-600 text-white font-semibold rounded-md shadow-lg hover:bg-purple-700 focus:outline-none focus:bg-purple-700">Get Recommendations</button>
                                <div id="song_name_container" class="mt-4"></div>
                            </form>
                        </div>

                        <!-- Recommendations -->
                        <div>
                            <h2 class="text-2xl font-semibold mb-6">Recommended Tracks</h2>
                            <ul class="recommended-tracks list-none p-0">
                                <?php
                                if (!empty($recommendedTracks)) {
                                    if (isset($recommendedTracks['error'])) {
                                        echo '<li>' . htmlspecialchars($recommendedTracks['error']) . '</li>';
                                    } else {
                                        foreach ($recommendedTracks['tracks'] as $track) {
                                            $image = $track['album']['images'][0]['url'];
                                            $trackName = htmlspecialchars($track['name'], ENT_QUOTES);

                                            $artists = array_map(function($artist) { return htmlspecialchars($artist['name'], ENT_QUOTES); }, $track['artists']);
                                            $artists = implode(', ', $artists);

                                            $uri = htmlspecialchars($track['uri'], ENT_QUOTES);
                                            $url = htmlspecialchars($track['external_urls']['spotify'], ENT_QUOTES);

                                            echo '<li class="flex items-center gap-4 mb-4">';
                                            echo '<img src="' . $image . '" alt="' . $trackName . '" class="rounded-md">';
                                            echo '<div class="flex-grow">';
                                            echo '<p class="text-lg font-semibold">' . $trackName . '</p>';
                                            echo '<p class="text-gray-400">' . htmlspecialchars($artists) . '</p>';
                                            echo '</div>';
                                            echo '<a href="' . $url . '" onclick="openSpotify(\'' . $uri . '\', \'' . $url . '\')" target="_blank" class="text-green-500 text-2xl">';
                                            echo '<i class="fa-brands fa-spotify"></i>';
                                            echo '</a>';
                                            echo '</li>';
                                        }
                                    }
                                } else {
                                    echo '<li>Enter a song and click "Get Recommendations" to get song recommendations.</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('input_type').addEventListener('change', function() {
            var songUrlInput = document.getElementById('song_url_input');
            var songNameArtistInput = document.getElementById('song_name_artist_input');
            if (this.value === 'song_name_artist') {
                songUrlInput.classList.add('hidden');
                songNameArtistInput.classList.remove('hidden');
            } else {
                songUrlInput.classList.remove('hidden');
                songNameArtistInput.classList.add('hidden');
            }
        });

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

        function refreshRecommendations() {
            location.reload();
        }
    </script>
</body>
</html>
