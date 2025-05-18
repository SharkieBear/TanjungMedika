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
// Ambil semua data pengguna
$stmt = $pdo->query("SELECT * FROM pengguna");
$penggunaList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission untuk tambah pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pengguna'])) {
    $nama_depan = $_POST['nama_depan'];
    $nama_belakang = $_POST['nama_belakang'];
    $email = $_POST['email'];
    $posisi = $_POST['posisi'];
    $kata_sandi = $_POST['kata_sandi']; // Simpan kata sandi dalam plain text

    // Handle upload foto profil
    $foto_profil = 'https://via.placeholder.com/150'; // Default jika tidak ada upload
    if ($_FILES['foto_profil']['name']) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES['foto_profil']['name']);
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
            $foto_profil = $target_file;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO pengguna (nama_depan, nama_belakang, email, posisi, kata_sandi, foto_profil) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nama_depan, $nama_belakang, $email, $posisi, $kata_sandi, $foto_profil]);

    // Redirect untuk menghindari resubmission
    header("Location: admin-pengguna.php");
    exit;
}

// Handle form submission untuk edit pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pengguna'])) {
    $id = $_POST['id'];
    $nama_depan = $_POST['nama_depan'];
    $nama_belakang = $_POST['nama_belakang'];
    $email = $_POST['email'];
    $posisi = $_POST['posisi'];

    // Handle upload foto profil
    $foto_profil = $_POST['foto_profil_old']; // Default ke foto lama
    if ($_FILES['foto_profil']['name']) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES['foto_profil']['name']);
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
            $foto_profil = $target_file;
        }
    }

    $stmt = $pdo->prepare("UPDATE pengguna SET nama_depan = ?, nama_belakang = ?, email = ?, posisi = ?, foto_profil = ? WHERE id = ?");
    $stmt->execute([$nama_depan, $nama_belakang, $email, $posisi, $foto_profil, $id]);

    // Redirect untuk menghindari resubmission
    header("Location: admin-pengguna.php");
    exit;
}

// Handle form submission untuk hapus pengguna
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id = ?");
    $stmt->execute([$id]);

    // Redirect untuk menghindari resubmission
    header("Location: admin-pengguna.php");
    exit;
}

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
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            top: 50%;
            transform: translateY(-50%);
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

        /* Tombol Edit dan Hapus */
        .btn-edit {
            background-color: #3b82f6;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .btn-edit:hover {
            background-color: #2563eb;
        }

        .btn-delete {
            background-color: #ef4444;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }
        
        /* Warna latar belakang baris tabel */
#penggunaTable tbody tr {
    background-color: #f3f4f6; /* Warna grey */
}

/* Hover effect untuk baris tabel */
#penggunaTable tbody tr:hover {
    background-color: #e5e7eb; /* Warna grey yang lebih gelap saat hover */
}

/* Warna latar belakang header tabel */
#penggunaTable thead tr {
    background-color: #d1d5db; /* Warna grey untuk header */
    color: #000; /* Warna teks header */
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
            <img src="images/Logo2.png" alt="Logo TanjungMedika">
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
                <a href="admin-laporan.php">
                    <i class="bi bi-file-earmark-text "></i> Laporan
                </a>
            </li>
            <li>
                <a href="admin-pengguna.php" class="bg-green-100">
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
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg p-4 bg-white">
                <!-- Header Section -->
                <div class="flex justify-between items-center mb-4">
    <div class="relative w-64">
        <input
            type="text"
            id="searchInput"
            placeholder="Cari pengguna..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
        />
        <i id="searchButton" class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer"></i>
    </div>
    <button
        onclick="openAddModal()"
        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 ml-4"
    >
        Tambah Pengguna
    </button>
</div>

                <!-- Pengguna Table -->
                <table class="w-full text-sm text-left text-gray-500" id="penggunaTable">
    <thead>
        <tr>
            <th class="px-6 py-3">Nama</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Posisi</th>
            <th class="px-6 py-3">Foto Profil</th>
            <th class="px-6 py-3">Kata Sandi</th> <!-- Kolom baru untuk Kata Sandi -->
            <th class="px-6 py-3 text-center">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($penggunaList as $pengguna): ?>
            <tr class="bg-white border-b hover:bg-gray-50">
                <!-- Nama -->
                <td class="px-6 py-4"><?php echo htmlspecialchars($pengguna['nama_depan'] . ' ' . $pengguna['nama_belakang']); ?></td>
                <!-- Email -->
                <td class="px-6 py-4"><?php echo htmlspecialchars($pengguna['email']); ?></td>
                <!-- Posisi -->
                <td class="px-6 py-4"><?php echo htmlspecialchars($pengguna['posisi']); ?></td>
                <!-- Foto Profil -->
                <td class="px-6 py-4">
                    <img src="<?php echo htmlspecialchars($pengguna['foto_profil']); ?>" alt="Foto Profil" class="w-10 h-10 rounded-full">
                </td>
                <!-- Kata Sandi -->
                <td class="px-6 py-4">
                    <?php
                    // Tampilkan kata sandi berdasarkan posisi
                    if ($pengguna['posisi'] === 'Admin') {
                        echo "********"; // Tampilkan bintang untuk admin
                    } else {
                        echo htmlspecialchars($pengguna['kata_sandi']); // Tampilkan kata sandi dalam plain text
                    }
                    ?>
                </td>
                <!-- Action Buttons -->
                <td class="px-6 py-4 text-center">
    <button
        class="btn-edit"
        onclick="openEditModal(
            <?php echo $pengguna['id']; ?>,
            '<?php echo $pengguna['nama_depan']; ?>',
            '<?php echo $pengguna['nama_belakang']; ?>',
            '<?php echo $pengguna['email']; ?>',
            '<?php echo $pengguna['posisi']; ?>',
            '<?php echo $pengguna['foto_profil']; ?>'
        )"
    >
        Edit
    </button>
    <button
        class="btn-delete"
        onclick="deletePengguna(<?php echo $pengguna['id']; ?>)"
    >
        Hapus
    </button>
</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
            </div>
        </div>
    </div>

    <!-- Add Pengguna Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content w-11/12 md:w-1/2 lg:w-1/3">
            <div class="modal-header">
                <h2 class="text-xl font-semibold">Tambah Pengguna</h2>
                <button onclick="closeAddModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin-pengguna" enctype="multipart/form-data">
                    <input type="hidden" name="add_pengguna" value="1">
                    <div class="mb-4 flex space-x-4">
                        <div class="w-1/2">
                            <label class="block text-gray-700 font-medium mb-2">Nama Depan</label>
                            <input
                                type="text"
                                name="nama_depan"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                                required
                            />
                        </div>
                        <div class="w-1/2">
                            <label class="block text-gray-700 font-medium mb-2">Nama Belakang</label>
                            <input
                                type="text"
                                name="nama_belakang"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                                required
                            />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Posisi</label>
                        <select
                            name="posisi"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Manajer">Manajer</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Kata Sandi</label>
                        <input
                            type="password"
                            name="kata_sandi"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Konfirmasi Kata Sandi</label>
                        <input
                            type="password"
                            name="konfirmasi_kata_sandi"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Foto Profil</label>
                        <input
                            type="file"
                            name="foto_profil"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                    </div>
                    <div class="modal-footer flex justify-end space-x-4">
                        <button
                            type="button"
                            class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600"
                            onclick="closeAddModal()"
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

    <!-- Edit Pengguna Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content w-11/12 md:w-1/2 lg:w-1/3">
            <div class="modal-header">
                <h2 class="text-xl font-semibold">Edit Pengguna</h2>
                <button onclick="closeEditModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="admin-pengguna.php" enctype="multipart/form-data">
                    <input type="hidden" name="edit_pengguna" value="1">
                    <input type="hidden" name="id" id="editPenggunaId">
                    <input type="hidden" name="foto_profil_old" id="editPenggunaFotoProfilOld">
                    <div class="mb-4 flex space-x-4">
                        <div class="w-1/2">
                            <label class="block text-gray-700 font-medium mb-2">Nama Depan</label>
                            <input
                                type="text"
                                name="nama_depan"
                                id="editPenggunaNamaDepan"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                                required
                            />
                        </div>
                        <div class="w-1/2">
                            <label class="block text-gray-700 font-medium mb-2">Nama Belakang</label>
                            <input
                                type="text"
                                name="nama_belakang"
                                id="editPenggunaNamaBelakang"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                                required
                            />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Email</label>
                        <input
                            type="email"
                            name="email"
                            id="editPenggunaEmail"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Posisi</label>
                        <select
                            name="posisi"
                            id="editPenggunaPosisi"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <option value="Admin">Admin</option>
                            <option value="Staff">Staff</option>
                            <option value="Manajer">Manajer</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Foto Profil</label>
                        <input
                            type="file"
                            name="foto_profil"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                        <img id="editPenggunaFotoProfilPreview" src="" alt="Foto Profil" class="w-20 h-20 rounded-full mt-2">
                    </div>
                    <div class="modal-footer flex justify-end space-x-4">
                        <button
                            type="button"
                            class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600"
                            onclick="closeEditModal()"
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
        // Fungsi untuk toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("active");
        }

        // Fungsi untuk membuka modal tambah pengguna
        function openAddModal() {
            document.getElementById("addModal").style.display = "block";
        }

        // Fungsi untuk menutup modal tambah pengguna
        function closeAddModal() {
            document.getElementById("addModal").style.display = "none";
        }

        // Fungsi untuk membuka modal edit pengguna
        function openEditModal(id, namaDepan, namaBelakang, email, posisi, fotoProfil) {
            document.getElementById("editPenggunaId").value = id;
            document.getElementById("editPenggunaNamaDepan").value = namaDepan;
            document.getElementById("editPenggunaNamaBelakang").value = namaBelakang;
            document.getElementById("editPenggunaEmail").value = email;
            document.getElementById("editPenggunaPosisi").value = posisi;
            document.getElementById("editPenggunaFotoProfilOld").value = fotoProfil;
            document.getElementById("editPenggunaFotoProfilPreview").src = fotoProfil;
            document.getElementById("editModal").style.display = "block";
        }

        // Fungsi untuk menutup modal edit pengguna
        function closeEditModal() {
            document.getElementById("editModal").style.display = "none";
        }

        // Fungsi untuk menghapus pengguna
        function deletePengguna(id) {
            if (confirm("Apakah Anda yakin ingin menghapus pengguna ini?")) {
                window.location.href = `admin-pengguna.php?delete_id=${id}`;
            }
        }
        
    // Fungsi untuk mencari pengguna
    function searchPengguna() {
        const searchInput = document.querySelector('input[type="text"][placeholder="Cari pengguna..."]');
        const searchTerm = searchInput.value.trim().toLowerCase(); // Ambil kata kunci dan hilangkan spasi
        const penggunaRows = document.querySelectorAll('#penggunaTable tbody tr'); // Ambil semua baris pengguna

        penggunaRows.forEach(row => {
            const nama = row.querySelector('td:nth-child(1)').textContent.toLowerCase(); // Ambil nama pengguna
            if (nama.includes(searchTerm)) {
                row.style.display = ''; // Tampilkan baris jika cocok
            } else {
                row.style.display = 'none'; // Sembunyikan baris jika tidak cocok
            }
        });
    }

    // Tambahkan event listener untuk tombol Enter
    document.querySelector('input[type="text"][placeholder="Cari pengguna..."]').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            searchPengguna(); // Jalankan pencarian saat tombol Enter ditekan
        }
    });

    // Tambahkan event listener untuk ikon kaca pembesar
    document.querySelector('.bi-search').addEventListener('click', function() {
        searchPengguna(); // Jalankan pencarian saat ikon kaca pembesar diklik
    });
    
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