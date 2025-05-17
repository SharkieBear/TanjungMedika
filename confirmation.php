<?php
session_start();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user']['id'])) {
    die("Anda harus login untuk mengakses halaman ini.");
}

// Koneksi ke database
$host = 'localhost';
$port = '3308'; 
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

// Ambil data pesanan terbaru dari database
$user_id = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM customer_orders WHERE user_id = :user_id ORDER BY order_date DESC LIMIT 1");
$stmt->execute(['user_id' => $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika pesanan tidak ditemukan
if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// Decode data JSON dari kolom `products`
$products = json_decode($order['products'], true);
$total = $order['total']; // Ambil total dari kolom `total`
$pickupTime = $order['pickup_time']; // Ambil waktu pengambilan
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - Tanjung Medika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="images/Logo.png" type="image/png">
    <style>
        /* General Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f3f4f6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .confirmation-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .confirmation-icon {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 80px;
            height: 80px;
            background-color: #e8f5e9;
            border-radius: 50%;
            margin: 0 auto 20px;
        }

        .confirmation-icon svg {
            width: 40px;
            height: 40px;
            color: #4CAF50;
        }

        .confirmation-message {
            text-align: center;
            margin-bottom: 20px;
        }

        .confirmation-message h2 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 10px;
        }

        .confirmation-message p {
            font-size: 16px;
            color: #666;
        }

        .order-summary, .pickup-details {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .order-summary h3, .pickup-details h3 {
            font-size: 18px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 10px;
        }

        .order-summary .item, .pickup-details p {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .order-summary .total {
            font-weight: 500;
            color: #111827;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }

        .back-to-home {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-home button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .back-to-home button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-box">
            <!-- Order Confirmation Icon and Message -->
            <div class="confirmation-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div class="confirmation-message">
                <h2>Pesanan Berhasil!</h2>
                <p>Terima kasih atas pesanan Anda. Kami sedang memprosesnya.</p>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3>Ringkasan Pesanan</h3>
                <?php if (!empty($products)) : ?>
                    <?php foreach ($products as $item) : ?>
                        <div class="item">
                            <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo htmlspecialchars($item['quantity']); ?></span>
                            <span>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ",", "."); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="total">
                        <span>Total</span>
                        <span>Rp <?php echo number_format($total, 0, ",", "."); ?></span>
                    </div>
                <?php else : ?>
                    <p>Keranjang belanja Anda kosong.</p>
                <?php endif; ?>
            </div>

            <!-- Pickup Details -->
            <div class="pickup-details">
                <h3>Detail Pengambilan</h3>
                <p><strong>Waktu:</strong> <?php echo htmlspecialchars($pickupTime); ?></p>
            </div>

            <!-- Back to Home Button -->
            <form method="POST" action="index">
                <div class="back-to-home">
                    <button type="submit">Kembali ke Beranda</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>