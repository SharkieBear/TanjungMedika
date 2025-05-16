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




// Handle form submission untuk membatalkan pesanan
if (isset($_GET['cancel_id'])) {
    $id = $_GET['cancel_id'];
    $stmt = $pdo->prepare("UPDATE customer_orders SET status = 'Dibatalkan' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: staff-pesanan");
    exit;
}

// Handle update status pesanan
if (isset($_GET['update_status'])) {
    $orderId = $_GET['order_id'];
    $newStatus = $_GET['new_status'];
    $stmt = $pdo->prepare("UPDATE customer_orders SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    header("Location: staff-pesanan");
    exit;
}

// Ambil data pesanan dari tabel customer_orders dengan filter status dan pencarian
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Ubah query untuk mengurutkan berdasarkan order_date DESC
$query = "SELECT co.id, co.total, co.prescription, co.pickup_time, co.status, co.order_date,
                 CONCAT(u.first_name, ' ', u.last_name) AS customer 
          FROM customer_orders co 
          JOIN users u ON co.user_id = u.id 
          WHERE co.total IS NOT NULL 
          AND co.pickup_time IS NOT NULL 
          AND co.status IS NOT NULL";

// Tambahkan ORDER BY
$query .= " ORDER BY co.order_date DESC";

if (!empty($statusFilter)) {
    $query .= " AND co.status = :status";
}

if (!empty($searchQuery)) {
    $query .= " AND (co.id LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
}

$stmt = $pdo->prepare($query);

if (!empty($statusFilter)) {
    $stmt->bindParam(':status', $statusFilter);
}

if (!empty($searchQuery)) {
    $searchParam = "%$searchQuery%";
    $stmt->bindParam(':search', $searchParam);
}

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['action']) && $_GET['action'] === 'get_order_detail') {
    $orderId = $_GET['id'];

    try {
        // Ambil data pesanan
        $stmt = $pdo->prepare("
            SELECT co.id, co.total, co.prescription, co.pickup_time, co.status, co.order_date,
                   co.products, co.prescription_files,
                   CONCAT(u.first_name, ' ', u.last_name) AS customer 
            FROM customer_orders co 
            JOIN users u ON co.user_id = u.id 
            WHERE co.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Pesanan tidak ditemukan.");
        }

        // Decode JSON products
        $products = json_decode($order['products'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Gagal mengurai data produk.");
        }

        // Gabungkan data
        $response = [
            'id' => $order['id'],
            'order_date' => $order['order_date'],
            'pickup_time' => $order['pickup_time'],
            'total' => $order['total'],
            'prescription' => $order['prescription_files'], // Gunakan prescription_files untuk foto resep
            'status' => $order['status'],
            'products' => $products
        ];

        header('Content-Type: application/json');
        echo json_encode($response);
    } catch (Exception $e) {
        // Tangani error dan kembalikan pesan error sebagai JSON
        header('Content-Type: application/json');
        http_response_code(500); // Set status code ke 500
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

error_reporting(E_ALL); // Menampilkan semua error
ini_set('display_errors', 1); // Mengaktifkan tampilan error di browser
ini_set('log_errors', 1); // Menyimpan error ke log file
ini_set('error_log', 'php-error.log'); // Lokasi file log error

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
        /* Custom CSS untuk tombol Detail dan Batal */
        .btn-detail {
            background-color: #3b82f6; /* Biru */
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .btn-detail:hover {
            background-color: #2563eb; /* Biru lebih gelap */
        }

        .btn-cancel {
            background-color: #ef4444; /* Merah */
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .btn-cancel:hover {
            background-color: #dc2626; /* Merah lebih gelap */
        }

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
            width: 90%;
            max-width: 600px;
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
        
        #prescriptionModal .modal-content {
    background-color: transparent;
    box-shadow: none;
    max-width: 90%;
    max-height: 90%;
}

#prescriptionModal img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
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
    </style>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="/images/Logo2.png" alt="Logo TanjungMedika">
        </div>
        <ul class="menu">
            <li>
                <a href="staff-dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="staff-pesanan.php" class="bg-green-100">
                    <i class="bi bi-cart"></i> Pesanan
                </a>
            </li>
            <li>
                <a href="staff-produk.php">
                    <i class="bi bi-box"></i> Produk
                </a>
            </li>
            <li>
                <a href="staff-kategori.php">
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
            <img src="/images/Logo2.png" alt="Logo TanjungMedika">
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
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg p-4 bg-white">
                <!-- Header Section -->
                <div class="flex justify-between items-center mb-4">
                    <div class="relative w-64">
    <input
        type="text"
        id="searchBox"
        placeholder="Cari pesanan..."
        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
        onkeydown="handleSearchInput(event)"
    />
    <i id="searchIcon" class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer"></i>
</div>
                    <select
                        id="filterStatus"
                        class="border p-2 rounded-lg"
                        onchange="filterOrders()"
                    >
                        <option value="">Semua Status</option>
                        <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Siap Diambil">Siap Diambil</option>
                        <option value="Selesai">Selesai</option>
                        <option value="Dibatalkan">Dibatalkan</option>
                    </select>
                </div>

                <!-- Order Table -->
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-200">
                        <tr>
                            <th class="px-4 py-3">ID Pesanan</th>
                            <th class="px-4 py-3">Nama Pelanggan</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3 text-center">Resep</th>
                            <th class="px-4 py-3">Waktu Pengambilan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center">Kelola</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody">
                        <?php foreach ($orders as $order): ?>
                            <tr class="bg-white border-b hover:bg-gray-50" data-id="<?php echo $order['id']; ?>" data-customer="<?php echo $order['customer']; ?>" data-total="<?php echo $order['total']; ?>" data-prescription="<?php echo $order['prescription']; ?>" data-pickup-time="<?php echo $order['pickup_time']; ?>" data-status="<?php echo $order['status']; ?>">
                                <td class="px-4 py-3">ORD<?php echo $order['id']; ?></td>
                                <td class="px-4 py-3"><?php echo $order['customer']; ?></td>
                                <td class="px-4 py-3">Rp. <?php echo number_format($order['total'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php echo $order['prescription'] ? "✔️" : "—"; ?>
                                </td>
                                <td class="px-4 py-3"><?php echo $order['pickup_time']; ?></td>
                                <td class="px-4 py-3"><?php echo $order['status']; ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center space-x-4">
                                        <button
                                            class="btn-detail"
                                            onclick="openDetailModal(<?php echo $order['id']; ?>)"
                                        >
                                            Detail
                                        </button>
                                        <button
                                            class="btn-cancel"
                                            onclick="cancelOrder(<?php echo $order['id']; ?>)"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Order Detail Modal -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="text-xl font-semibold">Detail Pesanan</h2>
            <button onclick="closeDetailModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
        </div>
        <div class="modal-body">
            <div id="orderDetailContent">
                <!-- Informasi Pesanan -->
                <div class="mb-4">
                    <p class="text-gray-600">ID Pesanan: <span class="font-semibold" id="modalOrderId"></span></p>
                    <p class="text-gray-600">Tanggal Pesanan: <span class="font-semibold" id="modalOrderDate"></span></p>
                    <p class="text-gray-600">Waktu Pengambilan: <span class="font-semibold" id="modalPickupTime"></span></p>
                </div>

                <!-- Foto Resep -->
                <div class="mb-4">
                    <p class="font-semibold">Foto Resep:</p>
                    <img id="modalPrescriptionImage" src="" alt="Foto Resep" class="w-48 h-48 object-cover border border-black rounded-lg">
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

<!-- Modal untuk Foto Resep -->
<div id="prescriptionModal" class="modal">
    <div class="modal-content" style="max-width: 90%; max-height: 90%;">
        <img id="fullSizePrescriptionImage" src="" alt="Foto Resep" style="width: 100%; height: auto;">
    </div>
</div>

                <!-- Tombol Update Status -->
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>)" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 w-full">
    Update Status
</button>
            </div>
        </div>
    </div>
</div>

    <script>
        // Fungsi untuk toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("active");
        }

        // Fungsi untuk membatalkan pesanan
        function cancelOrder(orderId) {
            if (confirm("Apakah Anda yakin ingin membatalkan pesanan ini?")) {
                window.location.href = `staff-pesanan?cancel_id=${orderId}`;
            }
        }
        
        function updateOrderStatus(orderId) {
    const newStatus = document.getElementById("modalStatus").value;
    window.location.href = `staff-pesanan?update_status=1&order_id=${orderId}&new_status=${newStatus}`;
}

        // Fungsi untuk membuka modal detail pesanan
function openDetailModal(orderId) {
    console.log(`Membuka modal untuk pesanan ID: ${orderId}`); // Log untuk debugging
    fetch(`staff-pesanan?action=get_order_detail&id=${orderId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error); // Jika ada pesan error dari server
            }
            console.log("Data pesanan diterima:", data); // Log untuk debugging

            // Tampilkan foto resep (jika ada)
            const prescriptionImage = data.prescription ? `<img src="uploads/prescriptions/${data.prescription}" alt="Foto Resep" class="w-48 h-48 object-cover border border-black rounded-lg">` : "Tidak ada resep";

            // Tampilkan list produk yang dipesan
            const productList = data.products.map(product => `
                <div class="flex justify-between items-center border p-4 rounded-lg shadow-sm">
                    <div>
                        <p class="font-semibold">${product.name}</p>
                        <p class="text-gray-600">Qty: ${product.quantity} x Rp${parseInt(product.price).toLocaleString()}</p>
                    </div>
                    <p class="font-semibold">Rp${(product.quantity * product.price).toLocaleString()}</p>
                </div>
            `).join("");

            // Tampilkan informasi pesanan
            const content = `
                <!-- Informasi Pesanan -->
                <div class="mb-4">
                    <p class="text-gray-600">ID Pesanan: <span class="font-semibold">ORD${data.id}</span></p>
                    <p class="text-gray-600">Tanggal Pesanan: <span class="font-semibold">${data.order_date}</span></p>
                    <p class="text-gray-600">Waktu Pengambilan: <span class="font-semibold">${data.pickup_time}</span></p>
                </div>

                <!-- Foto Resep -->
                <div class="mb-4">
                    <p class="font-semibold">Foto Resep:</p>
                    ${prescriptionImage}
                </div>

                <!-- List Produk yang Dipesan -->
                <div class="mb-4">
                    <p class="font-semibold">Produk yang Dipesan:</p>
                    <div class="space-y-4">
                        ${productList}
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
                    <select id="modalStatus" class="border p-2 rounded-lg w-full" style="border-collapse: collapse; width: 100%; border: 1px solid black;">
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
            document.getElementById("detailModal").style.display = "flex";
        })
        .catch(error => {
            console.error("Error saat mengambil detail pesanan:", error); // Log error
            alert(`Error: ${error.message}`); // Tampilkan pesan error ke pengguna
        });
}

        // Fungsi untuk menutup modal detail pesanan
        function closeDetailModal() {
            document.getElementById("detailModal").style.display = "none";
        }

        // Fungsi untuk menangani input pencarian
function handleSearchInput(event) {
    // Jika tombol "Enter" ditekan (kode 13)
    if (event.key === "Enter") {
        filterOrders();
    }
}

// Fungsi untuk memfilter pesanan berdasarkan status dan pencarian
function filterOrders() {
    const searchQuery = document.getElementById("searchBox").value.toLowerCase();
    const filterStatus = document.getElementById("filterStatus").value;
    const rows = document.querySelectorAll("#orderTableBody tr");

    rows.forEach(row => {
        const customer = row.getAttribute("data-customer").toLowerCase();
        const status = row.getAttribute("data-status");
        const matchesSearch = customer.includes(searchQuery);
        const matchesStatus = filterStatus === "" || status === filterStatus;

        if (matchesSearch && matchesStatus) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Tambahkan event listener untuk ikon kaca pembesar
document.getElementById("searchIcon").addEventListener("click", filterOrders);

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