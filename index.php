<?php

ini_set('include_path', '.:/home/kumina/kumina.dev/prod/utils:/home/kumina/kumina.dev/prod/components');

require_once('config.php');

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

define('SPOTIFY_API_BASE_URL', 'https://api.spotify.com/v1');
define('SPOTIFY_ACCESS_TOKEN_URL', 'https://accounts.spotify.com/api/token');

// Function for Spotify access token
function getAccessToken($clientId, $clientSecret) {
    $postData = http_build_query([
        'grant_type' => 'client_credentials',
    ]);
    $clientCredentials = base64_encode("$clientId:$clientSecret");

    $client = new Client();
    $response = $client->post(SPOTIFY_ACCESS_TOKEN_URL, [
        'headers' => [
            'Authorization' => 'Basic ' . $clientCredentials,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => $postData
    ]);

    $data = json_decode($response->getBody(), true);
    return $data['access_token'] ?? null;
}

// Function to make HTTP GET request
function makeGetRequest($url, $accessToken) {
    $client = new Client();
    $response = $client->get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken
        ]
    ]);

    return $response->getBody()->getContents();
}

// Function to get track ID from URL
function getTrackIdFromUrl($url) {
    preg_match('/track\/([a-zA-Z0-9]+)/', $url, $matches);
    return $matches[1] ?? false;
}

// Function to get playlist ID from URL
function getPlaylistIdFromUrl($url) {
    preg_match('/playlist\/([a-zA-Z0-9]+)/', $url, $matches);
    return $matches[1] ?? false;
}

// Function to get recommendations from Spotify
function getRecommendations($accessToken, $endpoint) {
    try {
        $response = makeGetRequest(SPOTIFY_API_BASE_URL . $endpoint, $accessToken);
        return json_decode($response, true);
    } catch (Exception $e) {
        return ['error' => 'Error fetching recommendations: ' . $e->getMessage()];
    }
}

// Function to get recommendations from track ID
function getRecommendationsFromTrackId($accessToken, $trackId) {
    return getRecommendations($accessToken, "/recommendations?seed_tracks=$trackId");
}

// Function to get recommendations from song name and artist
function getRecommendationsFromSongAndArtist($accessToken, $songName, $artistName) {
    $searchUrl = SPOTIFY_API_BASE_URL . "/search?q=$songName%20artist:$artistName&type=track&limit=1";

    $response = makeGetRequest($searchUrl, $accessToken);
    $searchResult = json_decode($response, true);

    if (isset($searchResult['tracks']['items'][0]['id'])) {
        $trackId = $searchResult['tracks']['items'][0]['id'];
        return getRecommendationsFromTrackId($accessToken, $trackId);
    } else {
        return ['error' => 'No recommendations found for the given song and artist.'];
    }
}

// Function to get recommendations from playlist ID
function getRecommendationsFromPlaylist($accessToken, $playlistId) {
    $playlistUrl = SPOTIFY_API_BASE_URL . "/playlists/$playlistId/tracks";

    $response = makeGetRequest($playlistUrl, $accessToken);
    $playlistData = json_decode($response, true);

    if (isset($playlistData['items']) && !empty($playlistData['items'])) {
        $trackIds = array_column($playlistData['items'], 'track.id');

        if (!empty($trackIds)) {
            $seed = $trackIds[array_rand($trackIds)];
            return getRecommendationsFromTrackId($accessToken, $seed);
        } else {
            return ['error' => 'No recommendations found for the given playlist.'];
        }
    } else {
        return ['error' => 'No recommendations found for the given playlist.'];
    }
}

$recommendedTracks = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : '';

    $accessToken = getAccessToken($clientId, $clientSecret);

    if ($inputType === 'song_url') {
        $input = isset($_POST['song_url']) ? $_POST['song_url'] : '';

        $trackId = getTrackIdFromUrl($input);
        if ($trackId) {
            $songInfoUrl = SPOTIFY_API_BASE_URL . "/tracks/$trackId";
            $options = [
                'http' => [
                    'header' => 'Authorization: Bearer ' . $accessToken,
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
            
            $recommendedTracks = getRecommendationsFromTrackId($accessToken, $trackId);
        } else {
            $recommendedTracks = ['error' => 'Invalid Spotify URL. Please enter a valid track URL.'];
        }
    } else if ($inputType === 'song_name_artist') {
        $input = isset($_POST['song_name_artist']) ? $_POST['song_name_artist'] : '';

        $inputParts = explode(' by ', $input);
        $songName = trim($inputParts[0]);
        $artistName = isset($inputParts[1]) ? trim($inputParts[1]) : '';

        if (!empty($songName) && !empty($artistName)) {
            echo '<script>';
            echo 'document.addEventListener("DOMContentLoaded", function() {';
            echo 'document.getElementById("song_name_container").innerHTML = "' . htmlspecialchars($songName) . ' by ' . htmlspecialchars($artistName) . '";';
            echo '});';
            echo '</script>';
        } else {
            die('Error: Invalid song name or artist name.');
        }

        $recommendedTracks = getRecommendationsFromSongAndArtist($accessToken, $songName, $artistName);
    } else if ($inputType === 'playlist_url') {
        $input = isset($_POST['playlist_url']) ? $_POST['playlist_url'] : '';

        $playlistId = getPlaylistIdFromUrl($input);
        if ($playlistId) {
            $recommendedTracks = getRecommendationsFromPlaylist($accessToken, $playlistId);
        } else {
            $recommendedTracks = ['error' => 'Invalid Spotify URL. Please enter a valid playlist URL.'];
        }
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
                                    <select name="input_type" id="input_type" class="block w-full p-4 bg-transparent border border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="song_url">Song URL</option>
                                        <option value="song_name_artist">Song Name & Artist</option>
                                        <!-- <option value="playlist_url">Playlist URL</option> -->
                                    </select>

                                    <input type="text" name="song_url" id="song_url" placeholder="Enter Song URL" class="block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <input type="text" name="song_name_artist" id="song_name_artist" placeholder="Enter Song & Artist Name (e.g., Song Title by Artist)" class="hidden block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <!-- <input type="text" name="playlist_url" id="playlist_url" placeholder="Enter Playlist URL" class="hidden block w-full p-4 bg-transparent border-b border-gray-400 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"> -->

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
                                                $image = !empty($track['album']['images']) ? htmlspecialchars($track['album']['images'][1]['url'], ENT_QUOTES) : '';
                                                $trackName = htmlspecialchars($track['name'], ENT_QUOTES);
                                                
                                                // Sanitize each artist name individually
                                                $artists = array_map(function($artist) {
                                                    return htmlspecialchars($artist['name'], ENT_QUOTES);
                                                }, $track['artists']);
                                                $artists = implode(', ', $artists);

                                                $url = htmlspecialchars($track['external_urls']['spotify'], ENT_QUOTES);
    
                                                echo '<li>';
                                                echo '<div>';
                                                echo '<img src="'. $image. '" alt="'. $trackName . '" width="100" height="100">';
                                                echo '</div>';
                                                echo '<div class="song-details">';
                                                echo '<span>' . $trackName . '</span>';
                                                echo '<span>' . htmlspecialchars($artists, ENT_QUOTES) . '</span>';
                                                echo '</div>';
                                                echo '<div class="logo">';
                                                echo '<a href="' . $url . '" target="_blank">';
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
            if (isset($recommendations->tracks) && !empty($recommendations->tracks)) {
                echo 'document.getElementById("refreshButton").classList.remove("hidden");';
            }
            ?>
        });

        document.getElementById('input_type').addEventListener('change', function() {
            var selectedOption = this.value;
            document.getElementById('song_url').classList.add('hidden');
            document.getElementById('song_name_artist').classList.add('hidden');
            // document.getElementById('playlist_url').classList.add('hidden');
            document.getElementById(selectedOption).classList.remove('hidden');
        });
    </script>
</body>
</html>
