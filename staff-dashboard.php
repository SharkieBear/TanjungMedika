<?php
session_start();

// Pengecekan apakah pengguna sudah login dan memiliki posisi Staff atau Manajer
if (!isset($_SESSION['pengguna']) || 
    ($_SESSION['pengguna']['posisi'] !== 'Staff' && $_SESSION['pengguna']['posisi'] !== 'Manajer')) {
    // Jika belum login atau bukan Staff/Manajer, redirect ke halaman login
    header('Location: login-pengguna.php');
    exit();
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




// Handle AJAX request for order detail
if (isset($_GET['action']) && $_GET['action'] == 'get_order_detail') {
    $orderId = $_GET['id'];
    $stmt = $pdo->prepare("
        SELECT co.id, co.total, co.status, co.order_date, co.pickup_time, co.products,
               CONCAT(u.first_name, ' ', u.last_name) AS customer_name 
        FROM customer_orders co 
        JOIN users u ON co.user_id = u.id 
        WHERE co.id = :id
    ");
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON products
    $order['products'] = json_decode($order['products'], true);

    header('Content-Type: application/json');
    echo json_encode($order);
    exit();
}

// Handle AJAX request for product detail
if (isset($_GET['action']) && $_GET['action'] == 'get_product_detail') {
    $productId = $_GET['id'];
    $stmt = $pdo->prepare("SELECT id, name, stock, expired FROM products WHERE id = :id");
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($product);
    exit();
}

// Handle AJAX request for updating stock and expiry date
if (isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    $productId = $_POST['id'];
    $newStock = $_POST['stock'];
    $expiredDate = $_POST['expired'];

    $stmt = $pdo->prepare("UPDATE products SET stock = :stock, expired = :expired WHERE id = :id");
    $stmt->execute([
        'stock' => $newStock,
        'expired' => $expiredDate,
        'id' => $productId
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Handle AJAX request for order detail (edit valdo)
if (isset($_GET['action']) && $_GET['action'] == 'get_order_detail') {
    header('Content-Type: application/json'); // Harus di awal

    try {
        $orderId = $_GET['id'];

        // Validasi ID (optional)
        if (!is_numeric($orderId)) {
            http_response_code(400);
            echo json_encode(['error' => 'ID tidak valid']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT co.id, co.total, co.status, CONCAT(u.first_name, ' ', u.last_name) AS customer_name 
            FROM customer_orders co 
            JOIN users u ON co.user_id = u.id 
            WHERE co.id = :id
        ");
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            echo json_encode($order);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Order tidak ditemukan']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}


// Ambil data ringkasan tugas
$pendingOrders = $pdo->query("SELECT COUNT(*) as count FROM customer_orders WHERE status = 'Menunggu Konfirmasi'")->fetch()['count'];
$lowStockProducts = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock < 10")->fetch()['count'];

// Ambil data pesanan menunggu dengan nama pelanggan dari tabel users
$orders = $pdo->query("
    SELECT co.id, co.total, co.status, CONCAT(u.first_name, ' ', u.last_name) AS customer_name 
    FROM customer_orders co 
    JOIN users u ON co.user_id = u.id 
    WHERE co.status = 'Menunggu Konfirmasi' 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil data stok habis (stok di bawah 10)
$stock = $pdo->query("SELECT * FROM products WHERE stock < 10 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Ambil data ringkasan tugas
$pendingOrders = $pdo->query("SELECT COUNT(*) as count FROM customer_orders WHERE status = 'Menunggu Konfirmasi'")->fetch()['count'];
$lowStockProducts = $pdo->query("SELECT COUNT(*) as count FROM products WHERE stock < 10")->fetch()['count'];

// Cek produk yang akan kadaluwarsa dalam 5 hari
$today = date('Y-m-d');
$fiveDaysLater = date('Y-m-d', strtotime('+5 days'));

$stmt = $pdo->prepare("SELECT name, expired, DATEDIFF(expired, CURDATE()) as days_left FROM products WHERE expired BETWEEN ? AND ? ORDER BY expired ASC");
$stmt->execute([$today, $fiveDaysLater]);
$expiringProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$showNotification = count($expiringProducts) > 0;

// Ambil data foto profil dari session
$foto_profil = isset($_SESSION['pengguna']['foto_profil']) ? $_SESSION['pengguna']['foto_profil'] : 'images/profile.jpg';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
    <link rel="icon" href="images/Logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Custom CSS untuk modal */
        /* Custom CSS untuk modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #ffffff;
            box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: -250px;
            padding: 20px;
            transition: left 0.3s ease;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar .logo img {
            width: 100px;
        }

        .sidebar .menu {
            list-style: none;
            padding: 0;
        }

        .sidebar .menu li {
            margin-bottom: 15px;
        }

        .sidebar .menu li a {
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }

        .sidebar .menu li a:hover {
            background-color: #f0fdf4;
        }

        .sidebar .menu li a i {
            margin-right: 10px;
        }

        .main-content {
            margin-left: 0;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.active {
            margin-left: 250px;
        }

        /* Header Styles */
        .header {
            background-color: white;
            padding: 10px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .header .burger-menu {
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 20px;
        }

        .header .logo {
            flex-grow: 1;
            text-align: left;
        }

        .header .logo img {
            height: 50px;
        }

        .header .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        
        .notification-icon {
    position: relative;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: red;
}

.notification-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #ef4444; /* merah */
    border: 2px solid white;
}

#notificationDropdown {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Mobile View (up to 640px) */
@media (max-width: 640px) {
    /* Container untuk tabel pesanan */
    .bg-white.rounded-lg.shadow-md {
        border-radius: 0;
        box-shadow: none;
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        padding: 20px;
        border-radius: 0.5rem;
    }
    
    /* Tabel itu sendiri */
    table {
        min-width: 100%;
        border-collapse: collapse;
    }

    /* Header dan sel tabel */
    th, td {
        padding: 0.5rem;
        font-size: 0.875rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    /* Header khusus */
    th {
        background-color: #f8fafc;
        font-weight: 600;
    }

    /* Sembunyikan kolom yang kurang penting di mobile */
    /* Untuk tabel Pesanan - sembunyikan kolom Pelanggan */
    table:first-of-type td:nth-child(2),
    table:first-of-type th:nth-child(2) {
        display: none;
    }

    /* Untuk tabel Stok - sembunyikan kolom Kategori */
    table:last-of-type td:nth-child(2),
    table:last-of-type th:nth-child(2) {
        display: none;
    }

    /* Tombol aksi */
    button {
        padding: 0.35rem 0.7rem;
        font-size: 0.8125rem;
        white-space: nowrap;
    }
}
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="images/Logo2.png" alt="Logo TanjungMedika">
        </div>
        <ul class="menu">
            <li>
                <a href="/staff-dashboard.php" class="bg-green-100">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="staff-pesanan.php">
                    <i class="bi bi-cart"></i> Pesanan
                </a>
            </li>
            <li>
                <a href="staff-produk.php">
                    <i class="bi bi-box"></i> Produk
                </a>
            </li>
            <li>
                <a href="/staff-kategori.php">
                    <i class="bi bi-tags"></i> Kategori
                </a>
            </li>
            <li>
                <a href="staff-inventory.php">
                    <i class="bi bi-boxes"></i> Inventory
                </a>
            </li>
            <li>
                <a href="staff-logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="burger-menu" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </div>
        <div class="logo">
            <img src="images/Logo2.png" alt="Logo TanjungMedika">
        </div>
        <div class="user-menu">
    <div class="relative mr-4">
        <i class="bi bi-bell text-2xl cursor-pointer" onclick="toggleNotifications()"></i>
        <?php if ($showNotification): ?>
            <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500"></span>
        <?php endif; ?>
        
        <!-- Dropdown Notifikasi -->
        <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-md shadow-lg z-50 border border-gray-200">
            <div class="p-3 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Notifikasi Kadaluwarsa</h3>
            </div>
            <div class="max-h-60 overflow-y-auto">
                <?php if ($showNotification): ?>
                    <?php foreach ($expiringProducts as $product): ?>
                        <div class="p-3 border-b border-gray-100 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($product['name']) ?></span>
                                <span class="text-xs px-2 py-1 rounded-full <?= $product['days_left'] <= 3 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $product['days_left'] ?> hari lagi
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Kadaluwarsa: <?= date('d M Y', strtotime($product['expired'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500">
                        <i class="bi bi-check-circle text-2xl text-green-500 block mb-2"></i>
                        Tidak ada produk yang akan kadaluwarsa
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <img src="<?= $foto_profil ?>" alt="Foto Profil" class="w-8 h-8 rounded-full">
</div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container mx-auto p-4 mt-16">
            <h2 class="text-3xl font-bold text-gray-800">Dashboard Staff</h2>

            <!-- Ringkasan Tugas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <p class="text-lg font-semibold text-gray-700">Jumlah Pesanan Menunggu Diproses:</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $pendingOrders; ?></p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md">
                    <p class="text-lg font-semibold text-gray-700">Jumlah Stok Produk Habis:</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $lowStockProducts; ?></p>
                </div>
            </div>

            <!-- Notifikasi -->
            <h2 class="text-3xl font-bold text-gray-800 mt-6">Notifikasi</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <!-- Pesanan Menunggu -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-blue-700 mb-4">Pesanan Menunggu</h3>
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                            <tr class="bg-blue-100">
                                <th class="border border-gray-300 p-2">ID Pesanan</th>
                                <th class="border border-gray-300 p-2">Pelanggan</th>
                                <th class="border border-gray-300 p-2">Total</th>
                                <th class="border border-gray-300 p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (!empty($orders)): ?>
        <?php foreach ($orders as $order): ?>
            <tr class="text-center">
                <td class="border border-gray-300 p-2">ORD<?php echo $order['id']; ?></td>
                <td class="border border-gray-300 p-2"><?php echo $order['customer_name']; ?></td>
                <td class="border border-gray-300 p-2">Rp<?php echo number_format($order['total'], 0, ',', '.'); ?></td>
                <td class="border border-gray-300 p-2">
                    <button
                        class="px-2 py-1 bg-blue-600 text-white rounded"
                        onclick="openOrderDetail(<?php echo $order['id']; ?>)"
                    >
                        Kelola
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4" class="text-center p-2">Tidak ada pesanan menunggu.</td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>

                <!-- Stok Habis -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-xl font-semibold text-red-700 mb-4">Stok Habis</h3>
                    <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                            <tr class="bg-red-100">
                                <th class="border border-gray-300 p-2">Produk</th>
                                <th class="border border-gray-300 p-2">Kategori</th>
                                <th class="border border-gray-300 p-2">Stok</th>
                                <th class="border border-gray-300 p-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php if (!empty($stock)): ?>
        <?php foreach ($stock as $item): ?>
            <tr class="text-center">
                <td class="border border-gray-300 p-2"><?php echo $item['name']; ?></td>
                <td class="border border-gray-300 p-2"><?php echo $item['category']; ?></td>
                <td class="border border-gray-300 p-2 text-red-600"><?php echo $item['stock']; ?></td>
                <td class="border border-gray-300 p-2">
                    <button
                        class="px-2 py-1 bg-red-600 text-white rounded"
                        onclick="openEditStock(<?php echo $item['id']; ?>)"
                    >
                        Edit
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="4" class="text-center p-2">Tidak ada produk dengan stok habis.</td>
        </tr>
    <?php endif; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Modal Pesanan Menunggu -->
<!-- Modal Pesanan Menunggu -->
<div id="orderDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="text-xl font-semibold">Detail Pesanan</h2>
            <button onclick="closeOrderDetailModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
        </div>
        <div class="modal-body">
            <div id="orderDetailContent">
                <!-- Informasi Pesanan -->
                <div class="mb-4">
                    <p class="text-gray-600">ID Pesanan: <span class="font-semibold" id="modalOrderId"></span></p>
                    <p class="text-gray-600">Tanggal Pesanan: <span class="font-semibold" id="modalOrderDate"></span></p>
                    <p class="text-gray-600">Waktu Pengambilan: <span class="font-semibold" id="modalPickupTime"></span></p>
                </div>

                <!-- List Produk yang Dipesan -->
                <div class="mb-4">
                    <p class="font-semibold">Produk yang Dipesan:</p>
                    <div id="modalProductList" class="w-full space-y-2"></div>
                </div>

                <!-- Garis Pemisah -->
                <hr class="my-4 border-t border-gray-300">

                <!-- Total Harga -->
                <div class="mb-4">
                    <p class="font-semibold">Total Harga: <span id="modalTotalPrice"></span></p>
                </div>

                <!-- Dropdown Status Pesanan -->
                <div class="mb-4">
                    <label for="modalStatus" class="block font-semibold">Status Pesanan:</label>
                    <select id="modalStatus" class="border p-2 rounded-lg w-full">
                        <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Siap Diambil">Siap Diambil</option>
                        <option value="Selesai">Selesai</option>
                        <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                </div>

                <!-- Tombol Update Status -->
                <button onclick="updateOrderStatus()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 w-full">
                    Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Stok dan Tanggal Kedaluwarsa -->
<div id="editStockModal" class="modal">
    <div class="modal-content w-11/12 md:w-1/2 lg:w-1/3">
        <div class="modal-header">
            <h2 class="text-xl font-semibold">Edit Stok dan Tanggal Kedaluwarsa</h2>
            <button onclick="closeEditStockModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editStockForm">
                <input type="hidden" name="id" id="editStockId">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Stok Baru</label>
                    <input
                        type="number"
                        name="stock"
                        id="editStockValue"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        required
                    />
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Tanggal Kedaluwarsa</label>
                    <input
                        type="date"
                        name="expired"
                        id="editExpiredDate"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        required
                    />
                </div>
                <div class="modal-footer flex justify-end space-x-4">
                    <button
                        type="button"
                        class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600"
                        onclick="closeEditStockModal()"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
// Fungsi untuk membuka modal detail pesanan
function openOrderDetail(orderId) {
    fetch(`staff-dashboard?action=get_order_detail&id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            const content = `
                <!-- Informasi Pesanan -->
                <div class="mb-4">
                    <p class="text-gray-600">ID Pesanan: <span class="font-semibold">ORD${data.id}</span></p>
                    <p class="text-gray-600">Tanggal Pesanan: <span class="font-semibold">${data.order_date}</span></p>
                    <p class="text-gray-600">Waktu Pengambilan: <span class="font-semibold">${data.pickup_time}</span></p>
                </div>

                <!-- List Produk yang Dipesan -->
                <div class="mb-4">
                    <p class="font-semibold">Produk yang Dipesan:</p>
                    <div class="space-y-4">
                        ${data.products.map(product => `
                            <div class="flex justify-between items-center border p-4 rounded-lg shadow-sm">
                                <div>
                                    <p class="font-semibold">${product.name}</p>
                                    <p class="text-gray-600">Qty: ${product.quantity} x Rp${parseInt(product.price).toLocaleString()}</p>
                                </div>
                                <p class="font-semibold">Rp${(product.quantity * product.price).toLocaleString()}</p>
                            </div>
                        `).join("")}
                    </div>
                </div>

                <!-- Garis Pemisah -->
                <hr class="my-4 border-t border-gray-300">

                <!-- Total Harga -->
                <div class="flex justify-between items-center mb-4">
                    <p class="font-semibold">Total Harga:</p>
                    <p class="font-semibold">Rp${parseInt(data.total).toLocaleString()}</p>
                </div>

                <!-- Dropdown Status Pesanan -->
                <div class="mb-4">
                    <label for="modalStatus" class="block font-semibold">Status Pesanan:</label>
                    <select id="modalStatus" class="border p-2 rounded-lg w-full">
                        <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Siap Diambil">Siap Diambil</option>
                        <option value="Selesai">Selesai</option>
                        <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                </div>

                <!-- Tombol Update Status -->
                <button onclick="updateOrderStatus(${data.id})" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 w-full">
                    Update Status
                </button>
            `;
            document.getElementById("orderDetailContent").innerHTML = content;
            document.getElementById("modalStatus").value = data.status;
            document.getElementById("orderDetailModal").style.display = "flex";
        });
}

// Fungsi untuk menutup modal detail pesanan
function closeOrderDetailModal() {
    document.getElementById("orderDetailModal").style.display = "none";
}

// Fungsi untuk mengupdate status pesanan
function updateOrderStatus(orderId) {
    const newStatus = document.getElementById("modalStatus").value;
    window.location.href = `staff-pesanan?update_status=1&order_id=${orderId}&new_status=${newStatus}`;
}

// Fungsi untuk membuka modal edit stok dan tanggal kedaluwarsa
function openEditStock(productId) {
    fetch(`staff-dashboard?action=get_product_detail&id=${productId}`)
        .then(response => response.json())
        .then(data => {
            debug.log("Hello")
            document.getElementById("editStockId").value = data.id;
            document.getElementById("editStockValue").value = data.stock;
            document.getElementById("editExpiredDate").value = data.expired;
            document.getElementById("editStockModal").style.display = "flex"; // Menggunakan flex untuk centering
        });
}

// Fungsi untuk menutup modal edit stok
function closeEditStockModal() {
    document.getElementById("editStockModal").style.display = "none";
}

// Fungsi untuk menyimpan perubahan stok dan tanggal kedaluwarsa
document.getElementById("editStockForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_stock');

    fetch("staff-dashboard", {
        method: "POST",
        body: formData,
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Stok dan tanggal kedaluwarsa berhasil diperbarui!");
                window.location.reload();
            } else {
                alert("Gagal memperbarui stok dan tanggal kedaluwarsa.");
            }
        });
});

// Fungsi untuk toggle sidebar
function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        sidebar.classList.toggle("active");
        mainContent.classList.toggle("active");
    }
    
    // Toggle dropdown notifikasi
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('hidden');
}

// Tutup dropdown saat klik di luar
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bellIcon = document.querySelector('.bi-bell');
    
    if (!dropdown.contains(event.target) && !bellIcon.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});
    </script>
</body>
</html>