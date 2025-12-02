<? php
/**
 * Admin Events API - Protected endpoint for event management
 * POST /api/admin/events.php - Create new event
 * PUT /api/admin/events.php? id=X - Update event
 * DELETE /api/admin/events.php?id=X - Delete event
 * GET /api/admin/events. php - Get all events (including drafts)
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Firebase-UID');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$db = require __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$auth = new Auth($db);
$method = $_SERVER['REQUEST_METHOD'];

// Require admin authentication for all operations
$currentUser = $auth->requireAdmin();

// GET - Fetch all events (including drafts) for admin
if ($method === 'GET') {
    $stmt = $db->query("
        SELECT e.*, 
               u.name as creator_name,
               GROUP_CONCAT(g.name) as genres,
               GROUP_CONCAT(g.slug) as genre_slugs
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN event_genres eg ON e.id = eg.event_id
        LEFT JOIN genres g ON eg.genre_id = g.id
        GROUP BY e.id
        ORDER BY e.created_at DESC
    ");
    $events = $stmt->fetchAll();
    
    foreach ($events as &$event) {
        $event['genres'] = $event['genres'] ? explode(',', $event['genres']) : [];
        $event['genre_slugs'] = $event['genre_slugs'] ? explode(',', $event['genre_slugs']) : [];
    }
    
    echo json_encode(['success' => true, 'events' => $events]);
    exit;
}

// POST - Create new event
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        exit;
    }
    
    // Insert event
    $stmt = $db->prepare("
        INSERT INTO events 
        (title, description, location, lat, lng, date, time, age_restriction, price, image_url, status, created_by)
        VALUES 
        (:title, :description, :location, :lat, :lng, :date, :time, :age_restriction, :price, :image_url, :status, :created_by)
    ");
    
    $stmt->execute([
        ':title' => $data['title'],
        ':description' => $data['description'] ?? null,
        ':location' => $data['location'] ?? null,
        ':lat' => $data['lat'] ?? null,
        ':lng' => $data['lng'] ?? null,
        ':date' => $data['date'] ?? null,
        ':time' => $data['time'] ?? null,
        ':age_restriction' => $data['age_restriction'] ?? null,
        ':price' => $data['price'] ?? null,
        ':image_url' => $data['image_url'] ?? null,
        ':status' => $data['status'] ?? 'published',
        ':created_by' => $currentUser['id']
    ]);
    
    $eventId = $db->lastInsertId();
    
    // Add genres if specified
    if (! empty($data['genres']) && is_array($data['genres'])) {
        $genreStmt = $db->prepare("INSERT INTO event_genres (event_id, genre_id) VALUES (:event_id, :genre_id)");
        foreach ($data['genres'] as $genreId) {
            $genreStmt->execute([':event_id' => $eventId, ':genre_id' => $genreId]);
        }
    }
    
    // Log action
    $auth->logAction($currentUser['id'], 'create_event', 'event', $eventId, json_encode(['title' => $data['title']]), $_SERVER['REMOTE_ADDR']);
    
    echo json_encode(['success' => true, 'event_id' => $eventId, 'message' => 'Event created successfully']);
    exit;
}

// PUT - Update existing event
if ($method === 'PUT') {
    if (! isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Event ID required']);
        exit;
    }
    
    $eventId = intval($_GET['id']);
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if event exists
    $checkStmt = $db->prepare("SELECT * FROM events WHERE id = :id");
    $checkStmt->execute([':id' => $eventId]);
    $existingEvent = $checkStmt->fetch();
    
    if (! $existingEvent) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }
    
    // Update event
    $stmt = $db->prepare("
        UPDATE events SET
        title = :title,
        description = :description,
        location = :location,
        lat = :lat,
        lng = :lng,
        date = :date,
        time = :time,
        age_restriction = :age_restriction,
        price = :price,
        image_url = :image_url,
        status = :status
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':title' => $data['title'] ?? $existingEvent['title'],
        ':description' => $data['description'] ?? $existingEvent['description'],
        ':location' => $data['location'] ?? $existingEvent['location'],
        ':lat' => $data['lat'] ?? $existingEvent['lat'],
        ':lng' => $data['lng'] ?? $existingEvent['lng'],
        ':date' => $data['date'] ?? $existingEvent['date'],
        ':time' => $data['time'] ??  $existingEvent['time'],
        ':age_restriction' => $data['age_restriction'] ?? $existingEvent['age_restriction'],
        ':price' => $data['price'] ?? $existingEvent['price'],
        ':image_url' => $data['image_url'] ?? $existingEvent['image_url'],
        ':status' => $data['status'] ??  $existingEvent['status'],
        ':id' => $eventId
    ]);
    
    // Update genres if specified
    if (isset($data['genres']) && is_array($data['genres'])) {
        // Remove old genres
        $db->prepare("DELETE FROM event_genres WHERE event_id = :event_id")->execute([':event_id' => $eventId]);
        
        // Add new genres
        $genreStmt = $db->prepare("INSERT INTO event_genres (event_id, genre_id) VALUES (:event_id, :genre_id)");
        foreach ($data['genres'] as $genreId) {
            $genreStmt->execute([':event_id' => $eventId, ':genre_id' => $genreId]);
        }
    }
    
    // Log action
    $auth->logAction($currentUser['id'], 'update_event', 'event', $eventId, json_encode($data), $_SERVER['REMOTE_ADDR']);
    
    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
    exit;
}

// DELETE - Delete event
if ($method === 'DELETE') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Event ID required']);
        exit;
    }
    
    $eventId = intval($_GET['id']);
    
    // Check if event exists
    $checkStmt = $db->prepare("SELECT title FROM events WHERE id = :id");
    $checkStmt->execute([':id' => $eventId]);
    $event = $checkStmt->fetch();
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }
    
    // Delete event (cascades will handle event_genres and user_favorites)
    $stmt = $db->prepare("DELETE FROM events WHERE id = :id");
    $stmt->execute([':id' => $eventId]);
    
    // Log action
    $auth->logAction($currentUser['id'], 'delete_event', 'event', $eventId, json_encode(['title' => $event['title']]), $_SERVER['REMOTE_ADDR']);
    
    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>