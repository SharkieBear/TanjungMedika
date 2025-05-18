<?php
header('Content-Type: application/json');

// Koneksi ke database
$host = 'localhost';
$port = '3308'; // Port default MySQL
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$dbname = 'tanjungmedika';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Koneksi berhasil!";
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}


// Mulai session untuk mengakses $_SESSION
session_start();

// Ambil data dari POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user']['id'])) {
    die(json_encode(['success' => false, 'message' => 'User not logged in']));
}

$userId = $_SESSION['user']['id'];
$endpoint = $data['endpoint'];
$auth = $data['keys']['auth'];
$p256dh = $data['keys']['p256dh'];

// Simpan ke database
try {
    $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, auth_key, p256dh_key) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          auth_key = VALUES(auth_key), 
                          p256dh_key = VALUES(p256dh_key)");
    $stmt->execute([$userId, $endpoint, $auth, $p256dh]);
    
    echo json_encode(['success' => true, 'message' => 'Subscription saved']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>