<?php
session_start();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user']['id'])) {
    die("Anda harus login untuk mengakses halaman ini.");
}

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

// Ambil data pesanan dari database
$user_id = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM customer_orders WHERE user_id = :user_id ORDER BY order_date DESC");
$stmt->execute(['user_id' => $user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk memformat tanggal
function formatDate($dateString) {
    return date("F j, Y, g:i a", strtotime($dateString));
}

// Fungsi untuk mendapatkan warna status
function getStatusColor($status) {
    $colors = [
        'Menunggu Konfirmasi' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'Diproses' => 'bg-blue-100 text-blue-800 border-blue-300',
        'Siap Diambil' => 'bg-orange-100 text-orange-800 border-orange-300',
        'Selesai' => 'bg-green-100 text-green-800 border-green-300',
        'Dibatalkan' => 'bg-red-100 text-red-800 border-red-300',
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-300';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Tanjung Medika</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="images/Logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<style>
    .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }

        .back-button:hover {
            background-color: #45a049;
        }
</style>
<body class="bg-gray-100">
    <div class="space-y-8 p-6 min-h-screen">
        <!-- Tombol Kembali ke Profile -->
        <a href="profile.php" class="back-button">
            <i class="fas fa-arrow-left"></i></a>
        </a>

        <h2 class="text-3xl font-bold text-gray-800">Riwayat Pesanan</h2>

        <?php if (empty($orders)) : ?>
            <div class="bg-white p-6 rounded-lg shadow-md text-center">
                <p class="text-gray-500">Tidak ada pesanan ditemukan.</p>
            </div>
        <?php else : ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order) : ?>
                    <a
                        href="OrderHistoryDetail?id=<?php echo $order['id']; ?>"
                        class="block bg-white p-6 rounded-lg shadow-md cursor-pointer transition hover:shadow-lg"
                    >
                        <!-- Ringkasan Pesanan -->
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Pesanan #<?php echo $order['id']; ?></h3>
                                <p class="text-sm text-gray-600"><?php echo formatDate($order['order_date']); ?></p>
                            </div>
                            <span
                                class="inline-flex items-center px-4 py-1 rounded-full text-sm font-medium border <?php echo getStatusColor($order['status']); ?>"
                            >
                                <?php echo $order['status']; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>