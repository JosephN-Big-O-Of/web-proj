<?php
header('Content-Type: application/json');
try {
    $db = require __DIR__ . '/db.php';

    // Haversine distance
    function distance_km($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
        $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
        $radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 10; // km
        $stmt = $db->query('SELECT * FROM events');
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($lat !== null && $lng !== null) {
            $filtered = [];
            foreach ($events as $e) {
                if (!isset($e['lat']) || !isset($e['lng'])) continue;
                $d = distance_km($lat, $lng, floatval($e['lat']), floatval($e['lng']));
                if ($d <= $radius) {
                    $e['distance_km'] = $d;
                    $filtered[] = $e;
                }
            }
            usort($filtered, function($a,$b){ return $a['distance_km'] <=> $b['distance_km']; });
            echo json_encode($filtered);
            exit;
        }
        echo json_encode($events);
        exit;
    }

    // Simple POST to create event (admin) - requires X-Admin-Secret header
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // read admin secret from central config
        $config = require __DIR__ . '/config.php';
        $adminSecret = $config['admin_secret'] ?? 'change-me-to-a-secure-value';
        $provided = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
        if ($provided !== $adminSecret) {
            http_response_code(403);
            echo json_encode(['error'=>'Forbidden']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        // Validation
        $errors = [];
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            $errors['title'] = 'Title is required';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Title must be 200 characters or fewer';
        }

        $description = trim($data['description'] ?? '');
        $location = trim($data['location'] ?? '');
        if ($location !== '' && mb_strlen($location) > 200) {
            $errors['location'] = 'Location must be 200 characters or fewer';
        }

        // lat/lng optional but if present must be valid
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;
        if ($lat !== null && $lat !== '') {
            if (!is_numeric($lat) || floatval($lat) < -90 || floatval($lat) > 90) {
                $errors['lat'] = 'Latitude must be a number between -90 and 90';
            } else {
                $lat = floatval($lat);
            }
        } else {
            $lat = null;
        }
        if ($lng !== null && $lng !== '') {
            if (!is_numeric($lng) || floatval($lng) < -180 || floatval($lng) > 180) {
                $errors['lng'] = 'Longitude must be a number between -180 and 180';
            } else {
                $lng = floatval($lng);
            }
        } else {
            $lng = null;
        }

        // date YYYY-MM-DD
        $date = $data['date'] ?? null;
        if ($date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
                $errors['date'] = 'Date must be in YYYY-MM-DD format';
            }
        } else {
            $date = null;
        }

        // time HH:MM
        $time = $data['time'] ?? null;
        if ($time) {
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                $errors['time'] = 'Time must be in HH:MM (24h) format';
            }
        } else {
            $time = null;
        }

        $age_restriction = $data['age_restriction'] ?? null;
        if ($age_restriction !== null && $age_restriction !== '') {
            if (!is_numeric($age_restriction) || intval($age_restriction) < 0) {
                $errors['age_restriction'] = 'Age restriction must be a non-negative integer';
            } else {
                $age_restriction = intval($age_restriction);
            }
        } else {
            $age_restriction = null;
        }

        $price = $data['price'] ?? null;
        if ($price !== null && $price !== '') {
            if (!is_numeric($price) || floatval($price) < 0) {
                $errors['price'] = 'Price must be a non-negative number';
            } else {
                $price = floatval($price);
            }
        } else {
            $price = 0;
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            exit;
        }

        $stmt = $db->prepare('INSERT INTO events (title,description,location,lat,lng,date,time,age_restriction,price,created_at)
            VALUES (:title,:description,:location,:lat,:lng,:date,:time,:age_restriction,:price,:created_at)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':location' => $location,
            ':lat' => $lat,
            ':lng' => $lng,
            ':date' => $date,
            ':time' => $time,
            ':age_restriction' => $age_restriction,
            ':price' => $price,
            ':created_at' => date('c')
        ]);
        echo json_encode(['success'=>true,'id'=>$db->lastInsertId()]);
        exit;
    }

    // Handle update (PUT) - update event by id
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // read admin secret from central config
        $config = require __DIR__ . '/config.php';
        $adminSecret = $config['admin_secret'] ?? 'change-me-to-a-secure-value';
        $provided = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
        if ($provided !== $adminSecret) {
            http_response_code(403);
            echo json_encode(['error'=>'Forbidden']);
            exit;
        }

        // parse input
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error'=>'Invalid JSON']);
            exit;
        }
        $id = isset($data['id']) ? intval($data['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing event id']); exit; }

        // build update fields with same validation as POST
        $fields = [];
        $params = [':id'=>$id];

        if (isset($data['title'])) {
            $title = trim($data['title']);
            if ($title === '' || mb_strlen($title) > 200) { http_response_code(400); echo json_encode(['error'=>'Invalid title']); exit; }
            $fields[] = 'title = :title'; $params[':title'] = $title;
        }
        if (isset($data['description'])) { $fields[] = 'description = :description'; $params[':description'] = trim($data['description']); }
        if (isset($data['location'])) { $loc = trim($data['location']); if (mb_strlen($loc)>200){http_response_code(400);echo json_encode(['error'=>'Invalid location']);exit;} $fields[]='location = :location'; $params[':location']=$loc; }
        if (isset($data['lat'])) { if ($data['lat'] === '' || is_null($data['lat'])) { $params[':lat']=null; $fields[]='lat = :lat'; } elseif (!is_numeric($data['lat'])||floatval($data['lat'])<-90||floatval($data['lat'])>90){http_response_code(400);echo json_encode(['error'=>'Invalid latitude']);exit;} else { $fields[]='lat = :lat'; $params[':lat']=floatval($data['lat']); } }
        if (isset($data['lng'])) { if ($data['lng'] === '' || is_null($data['lng'])) { $params[':lng']=null; $fields[]='lng = :lng'; } elseif (!is_numeric($data['lng'])||floatval($data['lng'])<-180||floatval($data['lng'])>180){http_response_code(400);echo json_encode(['error'=>'Invalid longitude']);exit;} else { $fields[]='lng = :lng'; $params[':lng']=floatval($data['lng']); } }
        if (isset($data['date'])) { if ($data['date'] && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data['date'])||strtotime($data['date'])===false)){http_response_code(400);echo json_encode(['error'=>'Invalid date']);exit;} $fields[]='date = :date'; $params[':date']=$data['date']; }
        if (isset($data['time'])) { if ($data['time'] && !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/',$data['time'])){http_response_code(400);echo json_encode(['error'=>'Invalid time']);exit;} $fields[]='time = :time'; $params[':time']=$data['time']; }
        if (isset($data['age_restriction'])) { if ($data['age_restriction']!=='' && (!is_numeric($data['age_restriction'])||intval($data['age_restriction'])<0)){http_response_code(400);echo json_encode(['error'=>'Invalid age_restriction']);exit;} $fields[]='age_restriction = :age_restriction'; $params[':age_restriction']=$data['age_restriction']!==''?intval($data['age_restriction']):null; }
        if (isset($data['price'])) { if ($data['price']!=='' && (!is_numeric($data['price'])||floatval($data['price'])<0)){http_response_code(400);echo json_encode(['error'=>'Invalid price']);exit;} $fields[]='price = :price'; $params[':price']=$data['price']!==''?floatval($data['price']):0; }

        if (empty($fields)) { http_response_code(400); echo json_encode(['error'=>'No fields to update']); exit; }

        $sql = 'UPDATE events SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success'=>true,'affected'=>$stmt->rowCount()]);
        exit;
    }

    // Handle delete (DELETE) - delete event by id
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $config = require __DIR__ . '/config.php';
        $adminSecret = $config['admin_secret'] ?? 'change-me-to-a-secure-value';
        $provided = $_SERVER['HTTP_X_ADMIN_SECRET'] ?? '';
        if ($provided !== $adminSecret) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

        // allow id via query string or JSON body
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['id'])) $id = intval($data['id']);
        }
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }
        $stmt = $db->prepare('DELETE FROM events WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        echo json_encode(['success'=>true,'deleted'=>$stmt->rowCount()]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
