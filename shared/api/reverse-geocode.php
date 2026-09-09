<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$lat = $_GET['lat'] ?? $_POST['lat'] ?? null;
$lng = $_GET['lng'] ?? $_POST['lng'] ?? null;

if (!$lat || !$lng) {
    http_response_code(400);
    echo json_encode(['error' => 'Latitude and longitude are required']);
    exit;
}

// Validate coordinates
$lat = floatval($lat);
$lng = floatval($lng);

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Reverse geocode using Nominatim
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1&accept-language=id";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'BAPAS-Application/1.0');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Request failed: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'API returned status code: ' . $httpCode]);
    exit;
}

$data = json_decode($response, true);

if (!$data) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON response']);
    exit;
}

// Build address from response
$address = '';

// Priority 1: Use display_name
if (!empty($data['display_name'])) {
    $address = trim($data['display_name']);
}
// Priority 2: Build from address components
else if (!empty($data['address'])) {
    $addrParts = [];
    
    // Road/Street
    if (!empty($data['address']['road'])) {
        if (!empty($data['address']['house_number'])) {
            $addrParts[] = $data['address']['house_number'] . ' ' . $data['address']['road'];
        } else {
            $addrParts[] = $data['address']['road'];
        }
    }
    
    // Village/Hamlet/Neighbourhood
    if (!empty($data['address']['village'])) {
        $addrParts[] = $data['address']['village'];
    } else if (!empty($data['address']['hamlet'])) {
        $addrParts[] = $data['address']['hamlet'];
    } else if (!empty($data['address']['neighbourhood'])) {
        $addrParts[] = $data['address']['neighbourhood'];
    }
    
    // Suburb/City District
    if (!empty($data['address']['suburb'])) {
        $addrParts[] = $data['address']['suburb'];
    } else if (!empty($data['address']['city_district'])) {
        $addrParts[] = $data['address']['city_district'];
    }
    
    // District
    if (!empty($data['address']['district'])) {
        $addrParts[] = $data['address']['district'];
    }
    
    // City/Town/Municipality
    if (!empty($data['address']['city'])) {
        $addrParts[] = $data['address']['city'];
    } else if (!empty($data['address']['town'])) {
        $addrParts[] = $data['address']['town'];
    } else if (!empty($data['address']['municipality'])) {
        $addrParts[] = $data['address']['municipality'];
    }
    
    // State/Province
    if (!empty($data['address']['state'])) {
        $addrParts[] = $data['address']['state'];
    } else if (!empty($data['address']['province'])) {
        $addrParts[] = $data['address']['province'];
    }
    
    // Postal code
    if (!empty($data['address']['postcode'])) {
        $addrParts[] = $data['address']['postcode'];
    }
    
    // Country
    if (!empty($data['address']['country'])) {
        $addrParts[] = $data['address']['country'];
    }
    
    $address = implode(', ', $addrParts);
}

echo json_encode([
    'success' => true,
    'address' => $address,
    'data' => $data
]);

