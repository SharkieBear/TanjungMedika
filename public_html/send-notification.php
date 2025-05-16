<?php
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

// Ambil data dari POST
$userId = $_POST['user_id'];
$title = $_POST['title'];
$message = $_POST['message'];
$url = $_POST['url'] ?? '/';

// Ambil subscription dari database
$stmt = $pdo->prepare("SELECT endpoint, auth_key, p256dh_key FROM push_subscriptions WHERE user_id = ?");
$stmt->execute([$userId]);
$subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kirim notifikasi ke setiap subscription
foreach ($subscriptions as $sub) {
    $endpoint = $sub['endpoint'];
    $auth = $sub['auth_key'];
    $p256dh = $sub['p256dh_key'];
    
    // Gunakan library web-push-php atau kirim langsung ke push service
    // Contoh sederhana (implementasi nyata membutuhkan VAPID keys)
    $data = json_encode([
        'title' => $title,
        'body' => $message,
        'url' => $url
    ]);
    
    // Ini adalah contoh sederhana, implementasi nyata membutuhkan enkripsi
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($endpoint, false, $context);
}

echo json_encode(['success' => true, 'message' => 'Notifications sent']);
?>