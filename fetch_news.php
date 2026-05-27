<?php
// --- Configuration ---
$maxItemsPerFeed = 5; // Max number of news items to return per feed
$cacheDuration = 15 * 60; // Cache duration in seconds (15 minutes)
$cacheDir = __DIR__ . '/cache'; // Cache directory within the same folder as this script

// --- CORS Headers ---
// Adjust this to your actual domain in production for better security
header("Access-Control-Allow-Origin: *"); // Allows all origins for now
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// --- Handle Preflight OPTIONS request (for CORS) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Response Array ---
$response = array('status' => 'error', 'message' => 'An unknown error occurred.', 'items' => []);

// --- Get URL from GET parameter ---
if (!isset($_GET['url']) || empty(trim($_GET['url']))) {
    $response['message'] = 'RSS feed URL is required.';
    echo json_encode($response);
    exit();
}

$feedUrl = trim($_GET['url']);

// Validate URL format (basic validation)
if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
    $response['message'] = 'Invalid RSS feed URL format.';
    echo json_encode($response);
    exit();
}

// --- Cache Implementation ---
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . md5($feedUrl) . '.json';

// Check if a valid cache file exists
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheDuration) {
    // Serve from cache
    $cachedData = file_get_contents($cacheFile);
    if ($cachedData) {
        // $response = json_decode($cachedData, true); // This would overwrite our default $response
        // Instead, just echo the cached data and exit
        echo $cachedData;
        exit();
    }
}

// --- Fetch RSS Feed ---
try {
    // Set a user agent and timeout for the request
    $context = stream_context_create([
        'http' => [
            'user_agent' => 'GridironGazetteFetcher/1.0 (+http://yourwebsite.com)', // Optional: Be a good internet citizen
            'timeout' => 10, // Timeout in seconds
        ]
    ]);

    // Suppress errors for file_get_contents and handle them manually
    $xmlString = @file_get_contents($feedUrl, false, $context);

    if ($xmlString === false) {
        throw new Exception("Failed to fetch the RSS feed. The source might be down or the URL is incorrect.");
    }

    // Load the XML string
    // Suppress errors for SimpleXMLElement and handle them manually
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlString);
    libxml_clear_errors();

    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMsg = "Failed to parse XML. ";
        foreach ($errors as $error) {
            $errorMsg .= trim($error->message) . " (Line: {$error->line}) ";
        }
        throw new Exception($errorMsg);
    }

    $newsItems = [];
    $itemCount = 0;

    // RSS 2.0 typically uses <channel><item>
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            if ($itemCount >= $maxItemsPerFeed) break;
            $newsItems[] = [
                'title' => (string) $item->title,
                'link' => (string) $item->link,
                'description' => strip_tags((string) $item->description), // Basic sanitization
                'pubDate' => isset($item->pubDate) ? date("M d, Y", strtotime((string)$item->pubDate)) : 'N/A'
            ];
            $itemCount++;
        }
    }
    // Atom feeds typically use <entry>
    elseif (isset($xml->entry)) {
         foreach ($xml->entry as $entry) {
            if ($itemCount >= $maxItemsPerFeed) break;
            $link = '';
            if (isset($entry->link)) {
                if (is_array($entry->link)) { // Atom can have multiple link tags
                    foreach($entry->link as $l) {
                        if (isset($l['rel']) && $l['rel'] == 'alternate') {
                            $link = (string)$l['href'];
                            break;
                        }
                    }
                    if (empty($link) && isset($entry->link[0]['href'])) { // Fallback to first link
                         $link = (string)$entry->link[0]['href'];
                    }
                } else {
                     $link = (string)$entry->link['href'];
                }
            }

            $newsItems[] = [
                'title' => (string) $entry->title,
                'link' => $link,
                'description' => isset($entry->summary) ? strip_tags((string) $entry->summary) : (isset($entry->content) ? strip_tags((string) $entry->content) : 'No description available.'),
                'pubDate' => isset($entry->published) ? date("M d, Y", strtotime((string)$entry->published)) : (isset($entry->updated) ? date("M d, Y", strtotime((string)$entry->updated)) : 'N/A')
            ];
            $itemCount++;
        }
    }


    if (!empty($newsItems)) {
        $response['status'] = 'success';
        $response['message'] = 'News fetched successfully.';
        $response['items'] = $newsItems;

        // Save to cache
        file_put_contents($cacheFile, json_encode($response));

    } else {
        $response['message'] = 'No news items found in the feed or feed format not recognized.';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    // Optionally log the error server-side: error_log("RSS Fetch Error: " . $e->getMessage() . " for URL: " . $feedUrl);
}

// --- Send JSON response ---
echo json_encode($response);
?>
