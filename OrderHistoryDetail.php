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

// Ambil order_id dari parameter URL
if (!isset($_GET['id'])) {
    die("ID pesanan tidak valid.");
}
$order_id = $_GET['id'];

// Ambil data pesanan dari database
$stmt = $pdo->prepare("SELECT * FROM customer_orders WHERE id = :order_id AND user_id = :user_id");
$stmt->execute(['order_id' => $order_id, 'user_id' => $_SESSION['user']['id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika pesanan tidak ditemukan
if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// Decode data JSON dari kolom `products`
$products = json_decode($order['products'], true);

// Fungsi untuk mendapatkan style berdasarkan status
function getStatusStyle($status) {
    $styles = [
        'Menunggu Konfirmasi' => 'background-color: #fef3c7; color: #d97706; border-color: #f59e0b;',
        'Diproses' => 'background-color: #dbeafe; color: #1d4ed8; border-color: #3b82f6;',
        'Siap Diambil' => 'background-color: #fef3c7; color: #92400e; border-color: #92400e;',
        'Selesai' => 'background-color: #dcfce7; color: #16a34a; border-color: #22c55e;',
        'Dibatalkan' => 'background-color: #fee2e2; color: #dc2626; border-color: #ef4444;',
    ];
    return $styles[$status] ?? 'background-color: #f3f4f6; color: #111827; border-color: #d1d5db;';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Tanjung Medika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        .order-detail {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .order-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
        }

        .order-info {
            margin-bottom: 20px;
        }

        .order-info p {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .order-products {
            margin-bottom: 20px;
        }

        .order-products h3 {
            font-size: 18px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 10px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 10px;
        }

        .product-item .product-info {
            flex-grow: 1;
        }

        .product-item .product-info h4 {
            font-size: 16px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 4px;
        }

        .product-item .product-info p {
            font-size: 14px;
            color: #666;
        }

        .product-item .product-price {
            font-size: 16px;
            font-weight: 500;
            color: #111827;
        }

        .order-total {
            text-align: right;
            margin-top: 20px;
        }

        .order-total h3 {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }

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
</head>
<body>
    <div class="container">
        
        <div class="order-detail">
            <!-- Tombol Kembali -->
            <a href="order_history.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
            
            <!-- Header Pesanan -->
            <div class="order-header">
                <h2>Detail Pesanan #<?php echo $order['id']; ?></h2>
                <span style="
                    padding: 6px 12px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 500;
                    text-transform: capitalize;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid transparent;
                    <?php echo getStatusStyle($order['status']); ?>
                ">
                    <?php echo $order['status']; ?>
                </span>
            </div>

            <!-- Informasi Pesanan -->
            <div class="order-info">
                <p><strong>Tanggal Pesanan:</strong> <?php echo date("F j, Y, g:i a", strtotime($order['order_date'])); ?></p>
                <p><strong>Waktu Pengambilan:</strong> <?php echo htmlspecialchars($order['pickup_time']); ?></p>
            </div>

            <!-- Daftar Produk -->
            <div class="order-products">
                <h3>Produk Dipesan</h3>
                <?php foreach ($products as $product) : ?>
                    <div class="product-item">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="product-info">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p>Jumlah: <?php echo htmlspecialchars($product['quantity']); ?></p>
                        </div>
                        <div class="product-price">
                            Rp <?php echo number_format($product['price'] * $product['quantity'], 0, ",", "."); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr>
            <!-- Total Pesanan -->
            <div class="order-total">
                <h3>Total: Rp <?php echo number_format($order['total'], 0, ",", "."); ?></h3>
            </div>

        
        </div>
    </div>
</body>
</html>