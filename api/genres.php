<?php
/**
 * Genres API - Public endpoint to get all event genres
 * GET /api/genres. php - Get all genres
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    $db = require __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query("
            SELECT g.*, 
                   COUNT(eg.event_id) as event_count
            FROM genres g
            LEFT JOIN event_genres eg ON g.id = eg.genre_id
            LEFT JOIN events e ON eg.event_id = e.id AND e.status = 'published'
            GROUP BY g.id
            ORDER BY g.name ASC
        ");
        
        $genres = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'genres' => $genres]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    error_log('Genres API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>