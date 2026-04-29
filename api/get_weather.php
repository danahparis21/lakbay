<?php
// api/get_weather.php - Using FREE Open-Meteo API
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$mountain_id = $_GET['mountain_id'] ?? null;

if (!$mountain_id) {
    echo json_encode(['error' => 'Mountain ID required']);
    exit();
}

// Get mountain coordinates
$stmt = $pdo->prepare("SELECT name, start_point_lat, start_point_lng FROM mountains WHERE id = ?");
$stmt->execute([$mountain_id]);
$mountain = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mountain || !$mountain['start_point_lat'] || !$mountain['start_point_lng']) {
    echo json_encode(['error' => 'Mountain coordinates not available']);
    exit();
}

function getWeatherIcon($code) {
    if ($code === 0) return '☀️';
    if ($code <= 2) return '⛅';
    if ($code <= 3) return '☁️';
    if ($code <= 49) return '🌫';
    if ($code <= 67) return '🌧';
    if ($code <= 77) return '❄️';
    if ($code <= 82) return '🌦';
    if ($code <= 99) return '⛈';
    return '🌤';
}

$url = "https://api.open-meteo.com/v1/forecast?latitude={$mountain['start_point_lat']}&longitude={$mountain['start_point_lng']}&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weather_code&current=temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation,weather_code&wind_speed_unit=kmh&timezone=Asia/Manila&forecast_days=5";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && $response) {
    $data = json_decode($response, true);
    
    $forecast = [];
    if (isset($data['daily'])) {
        for ($i = 0; $i < count($data['daily']['time']); $i++) {
            $code = $data['daily']['weather_code'][$i] ?? 0;
            $forecast[] = [
                'date' => $data['daily']['time'][$i],
                'temp_max' => round($data['daily']['temperature_2m_max'][$i] ?? 0),
                'temp_min' => round($data['daily']['temperature_2m_min'][$i] ?? 0),
                'precip_prob' => $data['daily']['precipitation_probability_max'][$i] ?? 0,
                'icon' => getWeatherIcon($code)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'mountain' => $mountain['name'],
        'current' => [
            'temp' => round($data['current']['temperature_2m'] ?? 0),
            'humidity' => $data['current']['relative_humidity_2m'] ?? 0,
            'wind' => round($data['current']['wind_speed_10m'] ?? 0),
            'precip' => $data['current']['precipitation'] ?? 0,
            'icon' => getWeatherIcon($data['current']['weather_code'] ?? 0)
        ],
        'forecast' => $forecast
    ]);
} else {
    echo json_encode(['error' => 'Failed to fetch weather data']);
}
?>