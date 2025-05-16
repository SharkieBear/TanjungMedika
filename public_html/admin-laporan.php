<?php
session_start();

// Pengecekan apakah pengguna sudah login
if (!isset($_SESSION['pengguna']) || $_SESSION['pengguna']['posisi'] !== 'Admin') {
    // Jika belum login atau bukan admin, redirect ke halaman login
    header('Location: login-pengguna.php');
    exit();
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

// Ambil data pesanan dari database
$stmt = $pdo->query("SELECT * FROM customer_orders");
$orderList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total harga untuk semua pesanan
$totalHarga = array_sum(array_column($orderList, 'total'));

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
    margin: 20px auto; /* Margin untuk jarak dari atas dan bawah */
    padding: 20px;
    border-radius: 8px;
    width: 90%; /* Lebar modal */
    max-width: 600px; /* Lebar maksimum */
    max-height: 90vh; /* Tinggi maksimum */
    overflow-y: auto; /* Scroll jika konten terlalu panjang */
    position: relative;
    top: 50%;
    transform: translateY(-50%); /* Posisikan di tengah vertikal */
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.modal-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-header button {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-footer {
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
    align-items: center; /* Pusatkan vertikal */
}

/* Tombol Sidebar */
.header .burger-menu {
    font-size: 1.5rem;
    cursor: pointer;
    margin-right: 20px; /* Jarak antara tombol sidebar dan logo */
}

/* Logo */
.header .logo {
    flex-grow: 1; /* Logo mengambil ruang yang tersedia */
    text-align: left; /* Logo di sebelah kanan tombol sidebar */
}

.header .logo img {
    height: 50px; /* Sesuaikan ukuran logo */
}

/* Menu Pengguna */
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

/* Tombol Edit */
.btn-edit {
    background-color: #3b82f6; /* Biru */
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.btn-edit:hover {
    background-color: #2563eb; /* Biru lebih gelap */
}

/* Tombol Hapus */
.btn-hapus {
    background-color: #ef4444; /* Merah */
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.btn-hapus:hover {
    background-color: #dc2626; /* Merah lebih gelap */
}

/* Tombol Cetak PDF */
        .btn-pdf {
            background-color: #ef4444;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s ease;
        }

        .btn-pdf:hover {
            background-color: #dc2626;
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
                <a href="admin-dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="admin-pesanan.php">
                    <i class="bi bi-cart"></i> Pesanan
                </a>
            </li>
            <li>
                <a href="admin-produk.php">
                    <i class="bi bi-box"></i> Produk
                </a>
            </li>
            <li>
                <a href="admin-kategori.php">
                    <i class="bi bi-tags"></i> Kategori
                </a>
            </li>
            <li>
                <a href="admin-inventory.php">
                    <i class="bi bi-boxes"></i> Inventory
                </a>
            </li>
            <li>
                <a href="admin-laporan.php" class="bg-green-100">
                    <i class="bi bi-file-earmark-text "></i> Laporan
                </a>
            </li>
            <li>
                <a href="admin-pengguna.php">
                    <i class="bi bi-people"></i> Kelola Pengguna
                </a>
            </li>
            <li>
                <a href="admin-logout.php">
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
                <!-- Header Section -->
<div class="flex flex-wrap items-center gap-4 mb-4">
    <!-- Search Box dan Tombol PDF dalam satu grup -->
    <div class="flex items-center gap-4">
        <div class="relative w-64">
            <input
                type="text"
                placeholder="Cari laporan..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                onkeypress="if(event.key === 'Enter') searchOrder()"
            />
            <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer" onclick="searchOrder()"></i>
        </div>
        
        <button 
            id="printPdfBtn"
            class="btn-pdf"
            onclick="generatePDF()"
        >
            <i class="bi bi-file-earmark-pdf mr-2"></i> Cetak PDF
        </button>
    </div>
    
    <!-- Filter Period -->
    <select
        id="filterPeriod"
        class="border p-2 rounded-lg ml-auto"
        onchange="filterOrders()"
    >
        <option value="">Semua Periode</option>
        <option value="Hari Ini">Hari Ini</option>
        <option value="Minggu Ini">Minggu Ini</option>
        <option value="Bulan Ini">Bulan Ini</option>
        <option value="Tahun Ini">Tahun Ini</option>
    </select>
</div>

                <!-- Order Table -->
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-200">
                        <tr>
                            <th class="px-6 py-3">ID Pesanan</th>
                            <th class="px-6 py-3">Total Harga</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Tanggal Pesanan</th>
                            <th class="px-6 py-3">Waktu Pengambilan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderList as $order): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4">ORD<?php echo $order['id']; ?></td>
                                <td class="px-6 py-4"><?php echo number_format($order['total']); ?></td>
                                <td class="px-6 py-4"><?php echo $order['status']; ?></td>
                                <td class="px-6 py-4"><?php echo $order['order_date']; ?></td>
                                <td class="px-6 py-4"><?php echo $order['pickup_time']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-green-200">
                            <td class="px-6 py-3 font-bold">Total</td>
                            <td class="px-6 py-3 font-bold" id="totalHarga"><?php echo number_format($totalHarga); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
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

        // Fungsi untuk mencari pesanan
        function searchOrder() {
            const searchTerm = document.querySelector('input[type="text"][placeholder="Cari laporan..."]').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const id = row.querySelector('td:first-child').textContent.toLowerCase();
                if (id.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Fungsi untuk memfilter pesanan berdasarkan periode
        function filterOrders() {
            const filterPeriod = document.getElementById('filterPeriod').value;
            const rows = document.querySelectorAll('tbody tr');
            let totalHarga = 0;

            rows.forEach(row => {
                const orderDate = new Date(row.querySelector('td:nth-child(4)').textContent);
                const today = new Date();
                let showRow = false;

                switch (filterPeriod) {
                    case 'Hari Ini':
                        if (orderDate.toDateString() === today.toDateString()) {
                            showRow = true;
                        }
                        break;
                    case 'Minggu Ini':
                        const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()));
                        if (orderDate >= startOfWeek) {
                            showRow = true;
                        }
                        break;
                    case 'Bulan Ini':
                        if (orderDate.getMonth() === today.getMonth() && orderDate.getFullYear() === today.getFullYear()) {
                            showRow = true;
                        }
                        break;
                    case 'Tahun Ini':
                        if (orderDate.getFullYear() === today.getFullYear()) {
                            showRow = true;
                        }
                        break;
                    default:
                        showRow = true;
                        break;
                }

                if (showRow) {
                    row.style.display = '';
                    totalHarga += parseFloat(row.querySelector('td:nth-child(2)').textContent.replace(/,/g, ''));
                } else {
                    row.style.display = 'none';
                }
            });

            // Update total harga
            document.getElementById('totalHarga').textContent = totalHarga.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
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
    <!-- Tambahkan setelah script lainnya -->
<script src="js/nerator.js"></script>
</body>
</html>