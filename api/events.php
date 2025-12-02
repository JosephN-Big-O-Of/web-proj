<?php
/**
 * Events API - Public endpoint with genre filtering and distance search
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $db = require __DIR__ . '/db.php';

    // Haversine distance calculation
    function distance_km($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get filters from query params
        $eventId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $lat = isset($_GET['lat']) ?  floatval($_GET['lat']) : null;
        $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
        $radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 10; // km
        $genre = $_GET['genre'] ?? null;
        $limit = isset($_GET['limit']) ?  intval($_GET['limit']) : 100;
        
        // Get single event by ID
        if ($eventId) {
            $stmt = $db->prepare("
                SELECT e.*, 
                       u.name as creator_name,
                       GROUP_CONCAT(g.name) as genres,
                       GROUP_CONCAT(g.slug) as genre_slugs,
                       GROUP_CONCAT(g.icon) as genre_icons
                FROM events e
                LEFT JOIN users u ON e.created_by = u.id
                LEFT JOIN event_genres eg ON e.id = eg.event_id
                LEFT JOIN genres g ON eg.genre_id = g.id
                WHERE e.id = :id AND e.status = 'published'
                GROUP BY e.id
            ");
            $stmt->execute([':id' => $eventId]);
            $event = $stmt->fetch();
            
            if ($event) {
                $event['genres'] = $event['genres'] ? explode(',', $event['genres']) : [];
                $event['genre_slugs'] = $event['genre_slugs'] ? explode(',', $event['genre_slugs']) : [];
                $event['genre_icons'] = $event['genre_icons'] ? explode(',', $event['genre_icons']) : [];
                echo json_encode(['success' => true, 'event' => $event]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Event not found']);
            }
            exit;
        }
        
        // Get all published events with optional genre filter
        $sql = "
            SELECT e.*, 
                   u.name as creator_name,
                   GROUP_CONCAT(g.name) as genres,
                   GROUP_CONCAT(g.slug) as genre_slugs,
                   GROUP_CONCAT(g.icon) as genre_icons
            FROM events e
            LEFT JOIN users u ON e. created_by = u.id
            LEFT JOIN event_genres eg ON e.id = eg.event_id
            LEFT JOIN genres g ON eg.genre_id = g. id
            WHERE e.status = 'published'
        ";
        
        $params = [];
        
        // Filter by genre if specified
        if ($genre) {
            $sql .= " AND e.id IN (
                SELECT eg2.event_id FROM event_genres eg2
                JOIN genres g2 ON eg2. genre_id = g2.id
                WHERE g2.slug = :genre
            )";
            $params[':genre'] = $genre;
        }
        
        $sql .= " GROUP BY e.id ORDER BY e.date ASC, e.time ASC";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert comma-separated genre strings to arrays
        foreach ($events as &$event) {
            $event['genres'] = $event['genres'] ? explode(',', $event['genres']) : [];
            $event['genre_slugs'] = $event['genre_slugs'] ? explode(',', $event['genre_slugs']) : [];
            $event['genre_icons'] = $event['genre_icons'] ? explode(',', $event['genre_icons']) : [];
        }
        
        // Apply distance filter if lat/lng provided
        if ($lat !== null && $lng !== null) {
            $filtered = [];
            foreach ($events as $e) {
                if (! isset($e['lat']) || ! isset($e['lng']) || $e['lat'] === null || $e['lng'] === null) {
                    continue;
                }
                $d = distance_km($lat, $lng, floatval($e['lat']), floatval($e['lng']));
                if ($d <= $radius) {
                    $e['distance_km'] = round($d, 2);
                    $filtered[] = $e;
                }
            }
            // Sort by distance
            usort($filtered, function($a, $b) {
                return $a['distance_km'] <=> $b['distance_km'];
            });
            echo json_encode(['success' => true, 'events' => $filtered, 'count' => count($filtered)]);
        } else {
            echo json_encode(['success' => true, 'events' => $events, 'count' => count($events)]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('Events API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>