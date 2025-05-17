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




// Handle AJAX requests pertama
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_product_data':
            if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
                exit;
            }

            $id = (int)$_GET['id'];
            try {
                $stmt = $pdo->prepare("SELECT id, name, stock as sisa_barang, tanggal_masuk, nomor_nota, barang_masuk, barang_keluar FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    echo json_encode(['status' => 'success', 'data' => $data]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
                }
            } catch (PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit;

        case 'delete_inventory':
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    $id = (int)$_GET['id'];
    try {
        // Hapus record dari database
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $e->getMessage()]);
    }
    exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_inventory') {
    $id = $_POST['id'];
    $barang_masuk = (int)$_POST['barang_masuk'];
    $barang_keluar = (int)$_POST['barang_keluar'];
    $tanggal_masuk = $_POST['tanggal_masuk'];
    $nomor_nota = $_POST['nomor_nota'];
    $stok_sekarang = (int)$_POST['stok_sekarang'];
    
    try {
        $pdo->beginTransaction();
        
        // Hitung stok baru
        $new_stock = $stok_sekarang + $barang_masuk - $barang_keluar;
        
        if ($new_stock < 0) {
            throw new Exception("Stok tidak boleh negatif");
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE products SET 
            tanggal_masuk = ?, 
            nomor_nota = ?, 
            barang_masuk = ?, 
            barang_keluar = ?,
            stock = ?
            WHERE id = ?");
        
        $stmt->execute([
            $tanggal_masuk,
            $nomor_nota,
            $barang_masuk,
            $barang_keluar,
            $new_stock,
            $id
        ]);
        
        $pdo->commit();
        
        // Ambil data terbaru untuk dikembalikan ke client
        $stmt = $pdo->prepare("SELECT id, name, stock as sisa_barang, tanggal_masuk, nomor_nota, barang_masuk, barang_keluar FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $updatedData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data berhasil diupdate',
            'data' => $updatedData
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal update: ' . $e->getMessage()
        ]);
    }
    exit;
}
}

// Ambil data produk untuk ditampilkan
$stmt = $pdo->query("
    SELECT 
        id,
        name,
        stock as sisa_barang,
        tanggal_masuk,
        nomor_nota,
        barang_masuk,
        barang_keluar
    FROM products
    ORDER BY name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cek produk yang akan kadaluwarsa dalam 5 hari
$today = date('Y-m-d');
$fiveDaysLater = date('Y-m-d', strtotime('+5 days'));

$stmt = $pdo->prepare("SELECT name, expired, DATEDIFF(expired, CURDATE()) as days_left FROM products WHERE expired BETWEEN ? AND ? ORDER BY expired ASC");
$stmt->execute([$today, $fiveDaysLater]);
$expiringProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$showNotification = count($expiringProducts) > 0;

$foto_profil = isset($_SESSION['pengguna']['foto_profil']) ? $_SESSION['pengguna']['foto_profil'] : 'images/profile.jpg';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika - Inventory</title>
    <link rel="icon" href="images/Logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
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
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-height: 90vh;
    width: 40%;
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

        /* Tombol Edit */
        .btn-edit {
            background-color: #3b82f6;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-edit:hover {
            background-color: #2563eb;
        }

        /* Tombol Hapus */
        .btn-hapus {
            background-color: #ef4444;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
            border: none;
            cursor: pointer;
            margin-left: 5px;
        }

        .btn-hapus:hover {
            background-color: #dc2626;
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

    /* Modal Responsif */
    .modal-content {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        max-height: 90vh;
        width: 90%; /* Lebih kecil di mobile */
        overflow-y: auto;
    }

    @media (min-width: 768px) {
        .modal-content {
            width: 70%; /* Lebih lebar di tablet */
        }
    }

    @media (min-width: 1024px) {
        .modal-content {
            width: 40%; /* Lebar penuh di desktop */
        }
    }

    /* Input yang lebih besar di mobile */
    input, select {
        font-size: 16px !important; /* Mencegah zoom di iOS */
        min-height: 44px !important; /* Ukuran touch target yang baik */
    }

    /* Tombol yang lebih besar di mobile */
    .modal-footer button {
        padding: 12px 24px !important;
        font-size: 16px;
    }

    /* Header modal yang lebih besar */
    .modal-header h2 {
        font-size: 1.5rem;
    }

    /* Jarak yang lebih longgar di mobile */
    .modal-body {
        padding: 20px;
    }

    .mb-4 {
        margin-bottom: 1.5rem;
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
                <a href="/staff-dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="/staff-pesanan.php">
                    <i class="bi bi-cart"></i> Pesanan
                </a>
            </li>
            <li>
                <a href="/staff-produk.php">
                    <i class="bi bi-box"></i> Produk
                </a>
            </li>
            <li>
                <a href="/staff-kategori.php">
                    <i class="bi bi-tags"></i> Kategori
                </a>
            </li>
            <li>
                <a href="staff-inventory.php" class="bg-green-100">
                    <i class="bi bi-boxes"></i> Inventory
                </a>
            </li>
            <li>
                <a href="/staff-logout.php">
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
                    <div class="flex items-center gap-4">
                        <div class="relative w-64">
                            <input
                                type="text"
                                placeholder="Cari inventory..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                                onkeypress="if(event.key === 'Enter') searchInventory()"
                            />
                            <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer" onclick="searchInventory()"></i>
                        </div>
                        <button class="btn-pdf" onclick="generatePDF()">
                            <i class="bi bi-file-earmark-pdf"></i> Cetak PDF
                        </button>
                    </div>
                    <select
                        id="filterPeriod"
                        class="border p-2 rounded-lg"
                        onchange="filterInventory()"
                    >
                        <option value="">Semua Periode</option>
                        <option value="Hari Ini">Hari Ini</option>
                        <option value="Minggu Ini">Minggu Ini</option>
                        <option value="Bulan Ini">Bulan Ini</option>
                        <option value="Tahun Ini">Tahun Ini</option>
                    </select>
                </div>

                <!-- Inventory Table -->
                <table class="w-full text-sm text-left text-gray-500" id="inventoryTable">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-200">
                        <tr>
                            <th class="px-6 py-3">Nama Barang</th>
                            <th class="px-6 py-3">Tanggal Masuk</th>
                            <th class="px-6 py-3">Nomor Nota</th>
                            <th class="px-6 py-3">Barang Masuk</th>
                            <th class="px-6 py-3">Barang Keluar</th>
                            <th class="px-6 py-3">Sisa Barang</th>
                            <th class="px-6 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr data-id="<?= $product['id'] ?>">
                                <td class="px-6 py-4"><?= htmlspecialchars($product['name']) ?></td>
                                <td class="px-6 py-4"><?= $product['tanggal_masuk'] ?? '-' ?></td>
                                <td class="px-6 py-4"><?= $product['nomor_nota'] ?? '-' ?></td>
                                <td class="px-6 py-4"><?= $product['barang_masuk'] ?></td>
                                <td class="px-6 py-4"><?= $product['barang_keluar'] ?></td>
                                <td class="px-6 py-4"><?= $product['sisa_barang'] ?></td>
                                <td class="px-6 py-4">
                                    <button class="btn-edit" onclick="openEditModal(<?= $product['id'] ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn-hapus" onclick="confirmDelete(<?= $product['id'] ?>)">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Inventory</h2>
                <button type="button" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editId">
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="editNama">Nama Barang</label>
                        <input type="text" id="editNama" class="w-full p-2 border rounded" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="editTanggal">Tanggal Masuk</label>
                        <input type="date" id="editTanggal" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="editNota">Nomor Nota</label>
                        <input type="text" id="editNota" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="editMasuk">Barang Masuk</label>
                        <input type="number" id="editMasuk" class="w-full p-2 border rounded" min="0" required oninput="calculateRemainingStock()">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2" for="editKeluar">Barang Keluar</label>
                        <input type="number" id="editKeluar" class="w-full p-2 border rounded" min="0" required oninput="calculateRemainingStock()">
                    </div>
                    <div class="mb-4">
    <label class="block text-gray-700 mb-2" for="editStokSekarang">Stok Sekarang</label>
    <input type="number" id="editStokSekarang" class="w-full p-2 border rounded" readonly>
</div>
<div class="mb-4">
    <label class="block text-gray-700 mb-2" for="editSisa">Perkiraan Sisa Stok Setelah Update</label>
    <input type="number" id="editSisa" class="w-full p-2 border rounded" readonly>
</div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400" onclick="closeEditModal()">Batal</button>
                <button type="button" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700" onclick="saveChanges()">Simpan</button>
            </div>
        </div>
    </div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Konfirmasi Hapus</h2>
            <button type="button" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus data inventory ini?</p>
            <input type="hidden" id="deleteId">
        </div>
        <div class="modal-footer">
            <button type="button" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400" onclick="closeDeleteModal()">Batal</button>
            <button type="button" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700" onclick="deleteItem()">Hapus</button>
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

        // Fungsi untuk mencari inventory
        function searchInventory() {
            const searchTerm = document.querySelector('input[type="text"][placeholder="Cari inventory..."]').value.toLowerCase();
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            rows.forEach(row => {
                const nama = row.querySelector('td:first-child').textContent.toLowerCase();
                if (nama.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Fungsi untuk memfilter inventory berdasarkan periode
        function filterInventory() {
            const filterPeriod = document.getElementById('filterPeriod').value;
            const rows = document.querySelectorAll('#inventoryTable tbody tr');

            rows.forEach(row => {
                const orderDate = new Date(row.querySelector('td:nth-child(2)').textContent);
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

                row.style.display = showRow ? '' : 'none';
            });
        }

        // Fungsi untuk modal edit
        function openEditModal(id) {
    fetch('?action=get_product_data&id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                const product = data.data;
                document.getElementById('editId').value = product.id;
                document.getElementById('editNama').value = product.name;
                document.getElementById('editTanggal').value = product.tanggal_masuk || '';
                document.getElementById('editNota').value = product.nomor_nota || '';
                document.getElementById('editMasuk').value = product.barang_masuk;
                document.getElementById('editKeluar').value = product.barang_keluar;
                document.getElementById('editStokSekarang').value = product.sisa_barang; // Tambah ini
                document.getElementById('editSisa').value = product.sisa_barang;
                
                document.getElementById('editModal').style.display = 'flex';
            } else {
                throw new Error(data.message || 'Failed to load data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memuat data: ' + error.message);
        });
}

        // Fungsi untuk menyimpan perubahan
        function saveChanges() {
    const id = document.getElementById('editId').value;
    const formData = {
        action: 'update_inventory',
        id: id,
        tanggal_masuk: document.getElementById('editTanggal').value,
        nomor_nota: document.getElementById('editNota').value,
        barang_masuk: document.getElementById('editMasuk').value,
        barang_keluar: document.getElementById('editKeluar').value,
        stok_sekarang: document.getElementById('editStokSekarang').value
    };

    // Validasi
    if (!formData.tanggal_masuk || !formData.nomor_nota) {
        alert('Tanggal masuk dan nomor nota harus diisi!');
        return;
    }

    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update tabel dengan data terbaru dari server
            const product = data.data;
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.cells[1].textContent = product.tanggal_masuk || '-';
                row.cells[2].textContent = product.nomor_nota || '-';
                row.cells[3].textContent = product.barang_masuk;
                row.cells[4].textContent = product.barang_keluar;
                row.cells[5].textContent = product.sisa_barang; // Pastikan ini diupdate
            }
            closeEditModal();
            alert('Data berhasil diperbarui!');
        } else {
            throw new Error(data.message || 'Update gagal');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

// Fungsi untuk memperbarui baris tabel
function updateTableRow(id, tanggal_masuk, nomor_nota, barang_masuk, barang_keluar, sisa_barang) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
        const cells = row.cells;
        cells[1].textContent = tanggal_masuk || '-';
        cells[2].textContent = nomor_nota || '-';
        cells[3].textContent = barang_masuk;
        cells[4].textContent = barang_keluar;
        cells[5].textContent = sisa_barang;
    }
}

        // Fungsi untuk konfirmasi hapus
        function deleteItem() {
    const id = document.getElementById('deleteId').value;
    
    fetch('?action=delete_inventory&id=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                // Hapus baris dari tabel
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) {
                    row.remove();
                }
                closeDeleteModal();
                alert('Data berhasil dihapus!');
            } else {
                throw new Error(data.message || 'Gagal menghapus data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan: ' + error.message);
        });
}

// Fungsi untuk konfirmasi hapus
function confirmDelete(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function calculateRemainingStock() {
    const stokSekarang = parseInt(document.getElementById('editStokSekarang').value) || 0;
    const masukBaru = parseInt(document.getElementById('editMasuk').value) || 0;
    const keluarBaru = parseInt(document.getElementById('editKeluar').value) || 0;
    
    // Hitung sisa stok setelah update
    const sisa = stokSekarang + (masukBaru - keluarBaru);
    document.getElementById('editSisa').value = sisa >= 0 ? sisa : 0;
    
    // Validasi
    if (keluarBaru > (stokSekarang + masukBaru)) {
        alert('Barang keluar tidak boleh lebih besar dari stok yang tersedia');
        document.getElementById('editKeluar').value = 0;
        document.getElementById('editSisa').value = stokSekarang + masukBaru;
    }
}

        // Tutup modal ketika mengklik di luar modal
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
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
    <script src="js/cetakin.js"></script>
</body>
</html>