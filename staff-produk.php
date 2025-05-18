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




// Ambil data produk dari database tanpa duplikat nama
$stmt = $pdo->query("
    SELECT p.* 
    FROM products p
    INNER JOIN (
        SELECT name, MAX(inventory_created_at) as latest_date 
        FROM products 
        GROUP BY name
    ) latest ON p.name = latest.name AND p.inventory_created_at = latest.latest_date
    ORDER BY p.inventory_created_at DESC, p.name ASC
");
$productList = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data kategori dari database
$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission untuk tambah produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    
    // Validasi: Cek apakah produk dengan nama yang sama sudah ada
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
    $stmt->execute([$name]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Produk dengan nama '{$name}' sudah ada!";
        header("Location: admin-produk");
        exit;
    }

    // Lanjutkan proses jika validasi berhasil
    $description = trim($_POST['description']);
    $golongan = $_POST['golongan'];
    $category = $_POST['category'];
    $prescription = isset($_POST['prescription']) ? (int)$_POST['prescription'] : 0;
    $expired = $_POST['expired'];
    $stock = (int)$_POST['stock'];
    $harga = (int)$_POST['harga'];

    // Handle file upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $uploadFilePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFilePath)) {
            $image = $uploadFilePath;
        } else {
            $_SESSION['error'] = "Gagal mengupload gambar.";
            header("Location: admin-produk");
            exit;
        }
    }

    // Simpan data ke database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products 
            (name, description, golongan, category, prescription, expired, stock, harga, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $description, $golongan, $category, 
            $prescription, $expired, $stock, $harga, $image
        ]);
        
        $_SESSION['success'] = "Produk berhasil ditambahkan!";
        header("Location: admin-produk");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menambahkan produk: " . $e->getMessage();
        header("Location: admin-produk");
        exit;
    }
}

// Handle form submission untuk edit produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    
    // Validasi: Cek apakah nama produk sudah digunakan oleh produk lain
    $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Produk dengan nama '{$name}' sudah ada!";
        header("Location: admin-produk");
        exit;
    }

    // Lanjutkan proses edit
    $description = trim($_POST['description']);
    $golongan = $_POST['golongan'];
    $category = $_POST['category'];
    $prescription = isset($_POST['prescription']) ? (int)$_POST['prescription'] : 0;
    $expired = $_POST['expired'];
    $stock = (int)$_POST['stock'];
    $harga = (int)$_POST['harga'];

    // Ambil data produk saat ini
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $currentProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentImage = $currentProduct['image'];

    // Handle file upload
    $image = $currentImage;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $uploadFilePath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFilePath)) {
            $image = $uploadFilePath;
            
            // Hapus gambar lama jika ada
            if ($currentImage && file_exists($currentImage)) {
                unlink($currentImage);
            }
        } else {
            $_SESSION['error'] = "Gagal mengupload gambar.";
            header("Location: admin-produk");
            exit;
        }
    }

    // Update data di database
    try {
        $stmt = $pdo->prepare("
            UPDATE products SET
                name = ?, description = ?, golongan = ?, category = ?,
                prescription = ?, expired = ?, stock = ?, harga = ?, image = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $description, $golongan, $category,
            $prescription, $expired, $stock, $harga, $image, $id
        ]);
        
        $_SESSION['success'] = "Produk berhasil diperbarui!";
        header("Location: admin-produk");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui produk: " . $e->getMessage();
        header("Location: admin-produk");
        exit;
    }
}

// Handle form submission untuk edit stok
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_stock'])) {
    $id = (int)$_POST['id'];
    $stock = (int)$_POST['stock'];

    try {
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$stock, $id]);
        
        $_SESSION['success'] = "Stok produk berhasil diperbarui!";
        header("Location: admin-produk");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui stok: " . $e->getMessage();
        header("Location: admin-produk");
        exit;
    }
}

// Handle penghapusan produk
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    
    try {
        // Hapus gambar terkait jika ada
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product['image'] && file_exists($product['image'])) {
            unlink($product['image']);
        }

        // Hapus produk dari database
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "Produk berhasil dihapus!";
        header("Location: admin-produk");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menghapus produk: " . $e->getMessage();
        header("Location: admin-produk");
        exit;
    }
}

// Cek produk yang akan kadaluwarsa dalam 5 hari
$today = date('Y-m-d');
$fiveDaysLater = date('Y-m-d', strtotime('+5 days'));

$stmt = $pdo->prepare("
    SELECT name, expired, DATEDIFF(expired, CURDATE()) as days_left 
    FROM products 
    WHERE expired BETWEEN ? AND ? 
    ORDER BY expired ASC
");
$stmt->execute([$today, $fiveDaysLater]);
$expiringProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$showNotification = count($expiringProducts) > 0;

// Ambil data foto profil dari session
$foto_profil = isset($_SESSION['pengguna']['foto_profil']) ? $_SESSION['pengguna']['foto_profil'] : 'images/profile.jpg';

// Tampilkan notifikasi
function displayNotification() {
    if (isset($_SESSION['error'])) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">'.htmlspecialchars($_SESSION['error']).'</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['success'])) {
        echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">'.htmlspecialchars($_SESSION['success']).'</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>';
        unset($_SESSION['success']);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="images/Logo.png" type="image/png">
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
    margin: 20px auto; /* Margin untuk jarak dari atas dan bawah */
    padding: 20px;
    border-radius: 8px;
    width: 90%; /* Lebar modal */
    max-width: 600px; /* Lebar maksimum */
    max-height: 80vh; /* Tinggi maksimum */
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
    align-items: center;
}

.header .burger-menu {
    font-size: 1.5rem;
    cursor: pointer;
    margin-right: 20px; /* Jarak antara tombol burger dan logo */
}

.header .logo {
    flex-grow: 1; /* Logo akan mengambil ruang yang tersedia */
    text-align: center; /* Pusatkan logo */
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

/* Custom CSS untuk upload area */
.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    background-color: #f9fafb;
    transition: background-color 0.2s ease;
}

.upload-area:hover {
    background-color: #f3f4f6;
}

.upload-area input[type="file"] {
    display: none;
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

/* Tombol Stok */
.btn-stok {
    background-color: #f59e0b; /* Kuning */
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.btn-stok:hover {
    background-color: #d97706; /* Kuning lebih gelap */
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

    /* ... (keep existing styles before this) ... */

    /* Enhanced Table Styles */
    .table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 1rem 0;
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    table {
        width: 100%;
        min-width: 1000px; /* Minimum width before scrolling */
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    th, td {
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }

    th {
        background-color: #f8fafc;
        font-weight: 600;
        color: #4a5568;
        position: sticky;
        top: 0;
        white-space: nowrap;
    }

    /* Zebra Striping */
    tr:nth-child(even) {
        background-color: #f8fafc;
    }

    tr:hover {
        background-color: #f0f4f8;
    }

    /* Image Cell */
    td img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .action-btn {
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
        border-radius: 0.25rem;
        white-space: nowrap;
        transition: all 0.2s;
    }

    /* Responsive Breakpoints */
    /* Minimized Browser (1025px-1200px) */
    @media (max-width: 1200px) {
        table {
            min-width: 900px;
        }
        
        th, td {
            padding: 0.6rem 0.8rem;
        }
    }

    /* Tablet Landscape (769px-1024px) */
    @media (max-width: 1024px) {
        table {
            min-width: 800px;
        }
        
        .table-container {
            border-radius: 0;
            box-shadow: none;
        }
    }

    /* Tablet Portrait (641px-768px) */
    @media (max-width: 768px) {
        table {
            min-width: 700px;
        }
        
        th, td {
            padding: 0.5rem;
        }
        
        /* Hide less important columns */
        td:nth-child(4), th:nth-child(4), /* Golongan */
        td:nth-child(6), th:nth-child(6) /* Resep */ {
            display: none;
        }
    }

    /* Mobile (up to 640px) */
    @media (max-width: 640px) {
        .table-container {
            margin: 0 -1rem;
            width: calc(100% + 2rem);
            border-radius: 0;
        }
        
        table {
            min-width: 600px;
        }
        
        /* Hide more columns */
        td:nth-child(3), th:nth-child(3), /* Deskripsi */
        td:nth-child(7), th:nth-child(7) /* Expired */ {
            display: none;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 0.3rem;
        }
    }

    /* Small Mobile (up to 400px) */
    @media (max-width: 400px) {
        td:nth-child(5), th:nth-child(5) /* Kategori */ {
            display: none;
        }
        
        td img {
            width: 40px;
            height: 40px;
        }
    }

    /* Prescription Indicator */
    .prescription-indicator {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        text-align: center;
        line-height: 20px;
    }

    /* Truncate Long Text */
    .truncate-text {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: inline-block;
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
                <a href="staff-pesanan.php">
                    <i class="bi bi-cart"></i> Pesanan
                </a>
            </li>
            <li>
                <a href="staff-produk.php" class="bg-green-100">
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
        <!-- Product Table Section -->
        <div class="container mx-auto p-4 mt-16">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg p-4 bg-white">
                <!-- Header Section -->
                <div class="flex justify-between items-center mb-4 relative">
    <div class="relative w-64"> <!-- Lebar search box diatur ke 16rem (256px) -->
        <input
            type="text"
            placeholder="Cari produk..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
        />
        <i class="bi bi-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
    </div>
    <button
        onclick="openAddModal()"
        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 ml-4"
    >
        Tambah Produk
    </button>
</div>

                <!-- Product Table -->
                <table class="w-full text-sm text-left text-gray-500">
    <thead class="text-xs text-gray-700 uppercase bg-gray-200">
        <tr>
            <th class="px-4 py-3">Foto</th>
            <th class="px-4 py-3">Nama Produk</th>
            <th class="px-4 py-3">Deskripsi</th>
            <th class="px-4 py-3">Golongan</th>
            <th class="px-4 py-3">Kategori</th>
            <th class="px-4 py-3 text-center">Resep</th>
            <th class="px-4 py-3">Expired</th>
            <th class="px-4 py-3">Stok</th>
            <th class="px-4 py-3">Harga</th>
            <th class="px-4 py-3 text-center">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($productList as $product): ?>
            <tr class="bg-white border-b hover:bg-gray-50">
                <td class="px-4 py-3">
                    <img
                        src="<?php echo $product['image']; ?>"
                        alt="Product Image"
                        class="w-16 h-16 rounded"
                    />
                </td>
                <td class="px-4 py-3"><?php echo $product['name']; ?></td>
                <td class="px-4 py-3 max-w-xs truncate" title="<?php echo $product['description']; ?>">
                    <?php echo $product['description']; ?>
                </td>
                <td class="px-4 py-3"><?php echo $product['golongan']; ?></td>
                <td class="px-4 py-3"><?php echo $product['category']; ?></td>
                <td class="px-4 py-3 text-center">
    <?php if ($product['prescription'] == 1): ?>
        <span style="color: green;">✔️</span> <!-- Centang hijau untuk Resep -->
    <?php else: ?>
        <span style="color: red;">❌</span> <!-- X merah untuk Non-Resep -->
    <?php endif; ?>
</td>
                <td class="px-4 py-3"><?php echo $product['expired']; ?></td>
                <td class="px-4 py-3"><?php echo $product['stock']; ?></td>
                <td class="px-4 py-3">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></td>
                <td class="px-4 py-3 text-center">
                    <div class="flex justify-center space-x-4">
                        <button class="btn-edit" onclick="openEditModal(<?php echo $product['id']; ?>)">Edit</button>
                        <button class="btn-stok" onclick="openEditStockModal(<?php echo $product['id']; ?>)">Stok</button>
                        <button class="btn-hapus" onclick="deleteProduct(<?php echo $product['id']; ?>)">Hapus</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
            </div>
        </div>
    </div>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
    <div class="modal-content w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2"> <!-- Sesuaikan ukuran modal -->
        <div class="modal-header">
            <h2 class="text-xl font-semibold">Tambah Produk</h2>
            <button onclick="closeAddModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Tambahkan enctype="multipart/form-data" untuk upload file -->
            <form method="POST" action="staff-produk" enctype="multipart/form-data">
                <input type="hidden" name="add_product" value="1">

                <!-- Foto Produk (1 Kolom) -->
                <div class="mb-6">
    <label class="block text-gray-700 font-medium mb-2">Foto Produk</label>
    <div class="flex items-center justify-center w-full">
        <label for="image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                <i class="bi bi-image text-2xl text-gray-400"></i>
                <p class="mb-2 text-sm text-gray-500">
                    <span class="font-semibold">Klik untuk upload</span> atau drag and drop
                </p>
                <p class="text-xs text-gray-500">Format: PNG, JPG, JPEG (Max 2MB)</p>
            </div>
            <input id="image" name="image" type="file" class="hidden" accept="image/png, image/jpeg, image/jpg" />
        </label>
    </div>
    <!-- Tambahkan elemen untuk menampilkan pratinjau gambar -->
    <img id="imagePreview" src="#" alt="Preview Image" class="mt-2 w-16 h-16 rounded" style="display: none;" />
</div>

                <!-- Input dalam 2 Kolom -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Kolom 1 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Nama Produk</label>
                        <input
                            type="text"
                            name="name"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <!-- Kolom 2 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Golongan Obat</label>
                        <select
                            name="golongan"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <option value="Obat Keras">Obat Keras</option>
                            <option value="Obat Bebas">Obat Bebas</option>
                            <option value="Obat Bebas Terbatas">Obat Bebas Terbatas</option>
                        </select>
                    </div>
                    <!-- Kolom 1 -->
                    <div>
    <label class="block text-gray-700 font-medium mb-2">Kategori Obat</label>
    <select
        name="category"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
    >
        <?php foreach ($categories as $category): ?>
            <option value="<?php echo $category['name']; ?>"><?php echo $category['name']; ?></option>
        <?php endforeach; ?>
    </select>
</div>
                    <!-- Kolom 2 -->
                    <div>
    <label class="block text-gray-700 font-medium mb-2">Resep atau Non-Resep</label>
    <select
        name="prescription"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
    >
        <option value="0">Non-Resep</option> <!-- Non-Resep harus di atas -->
        <option value="1">Resep</option>
    </select>
</div>
                    <!-- Kolom 1 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Tanggal Kedaluwarsa</label>
                        <input
                            type="date"
                            name="expired"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                    </div>
                    <!-- Kolom 2 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Stok</label>
                        <input
                            type="number"
                            name="stock"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                    </div>
                </div>
                
                <div>
    <label class="block text-gray-700 font-medium mb-2">Harga</label>
    <input
        type="number"
        name="harga"
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
        required
    />
</div>

                <!-- Deskripsi (1 Kolom) -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Deskripsi Produk</label>
                    <textarea
                        name="description"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        rows="4"
                        placeholder="Masukkan deskripsi produk"
                    ></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer flex justify-end space-x-4">
                    <button
                        type="button"
                        class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition duration-200"
                        onclick="closeAddModal()"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-200"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Edit Product Modal -->
    <!-- Edit Product Modal -->
<div id="editModal" class="modal">
    <div class="modal-content w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2">
        <div class="modal-header">
            <h2 class="text-xl font-semibold">Edit Produk</h2>
            <button onclick="closeEditModal()" class="text-gray-600 hover:text-gray-900">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="staff-produk" enctype="multipart/form-data">
                <input type="hidden" name="edit_product" value="1">
                <input type="hidden" name="id" id="editProductId">

                <!-- Foto Produk (1 Kolom) -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Foto Produk</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="editImage" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <i class="bi bi-image text-2xl text-gray-400"></i>
                                <p class="mb-2 text-sm text-gray-500">
                                    <span class="font-semibold">Klik untuk upload</span> atau drag and drop
                                </p>
                                <p class="text-xs text-gray-500">Format: PNG, JPG, JPEG (Max 2MB)</p>
                            </div>
                            <input id="editImage" name="image" type="file" class="hidden" accept="image/png, image/jpeg, image/jpg" />
                        </label>
                    </div>
                    <!-- Tambahkan elemen untuk menampilkan pratinjau gambar -->
                    <img id="editImagePreview" src="#" alt="Preview Image" class="mt-2 w-16 h-16 rounded" style="display: none;" />
                </div>

                <!-- Input dalam 2 Kolom -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Kolom 1 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Nama Produk</label>
                        <input
                            type="text"
                            name="name"
                            id="editProductName"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                            required
                        />
                    </div>
                    <!-- Kolom 2 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Golongan Obat</label>
                        <select
                            name="golongan"
                            id="editProductGolongan"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <option value="Obat Keras">Obat Keras</option>
                            <option value="Obat Bebas">Obat Bebas</option>
                            <option value="Obat Bebas Terbatas">Obat Bebas Terbatas</option>
                        </select>
                    </div>
                    <!-- Kolom 1 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Kategori Obat</label>
                        <select
                            name="category"
                            id="editProductCategory"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['name']; ?>"><?php echo $category['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Kolom 2 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Resep atau Non-Resep</label>
                        <select
                            name="prescription"
                            id="editProductPrescription"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        >
                            <option value="0">Non-Resep</option> <!-- Nilai 0 untuk Non-Resep -->
        <option value="1">Resep</option> <!-- Nilai 1 untuk Resep -->
                        </select>
                    </div>
                    <!-- Kolom 1 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Tanggal Kedaluwarsa</label>
                        <input
                            type="date"
                            name="expired"
                            id="editProductExpired"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                    </div>
                    <!-- Kolom 2 -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Stok</label>
                        <input
                            type="number"
                            name="stock"
                            id="editProductStock"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        />
                    </div>
                </div>

                <!-- Harga (1 Kolom Penuh) -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Harga</label>
                    <input
                        type="number"
                        name="harga"
                        id="editProductHarga"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                    />
                </div>

                <!-- Deskripsi (1 Kolom) -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">Deskripsi Produk</label>
                    <textarea
                        name="description"
                        id="editProductDescription"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"
                        rows="4"
                        placeholder="Masukkan deskripsi produk"
                    ></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer flex justify-end space-x-4">
                    <button
                        type="button"
                        class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition duration-200"
                        onclick="closeEditModal()"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-200"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Edit Stock Modal -->
<div id="editStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Stok</h2>
            <button onclick="closeEditStockModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="staff-produk">
                <input type="hidden" name="edit_stock" value="1">
                <input type="hidden" name="id" id="editStockProductId">
                <div class="mb-4">
                    <label class="block text-gray-700">Stok</label>
                    <input
                        type="number"
                        name="stock"
                        id="editStockProductStock"
                        class="w-full px-4 py-2 border rounded-lg"
                        required
                    />
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                        onclick="closeEditStockModal()"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Konversi data produk dari PHP ke JavaScript
    const productList = <?php echo json_encode($productList); ?>;

    // Fungsi untuk toggle sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        sidebar.classList.toggle("active");
        mainContent.classList.toggle("active");
    }

    // Fungsi untuk membuka modal tambah produk
    function openAddModal() {
        document.getElementById("addModal").style.display = "block";
    }

    // Fungsi untuk menutup modal tambah produk
    function closeAddModal() {
        document.getElementById("addModal").style.display = "none";
    }

    // Fungsi untuk membuka modal edit produk
    function openEditModal(productId) {
    const product = productList.find(p => p.id == productId); // Gunakan == (loose comparison)
    if (product) {
        document.getElementById('editProductId').value = product.id;
        document.getElementById('editProductName').value = product.name;
        document.getElementById('editProductDescription').value = product.description;
        document.getElementById('editProductGolongan').value = product.golongan;
        document.getElementById('editProductCategory').value = product.category;
        document.getElementById('editProductPrescription').value = product.prescription ? '1' : '0';
        document.getElementById('editProductExpired').value = product.expired;
        document.getElementById('editProductStock').value = product.stock;
        document.getElementById('editProductHarga').value = product.harga;

        const imagePreview = document.getElementById('editImagePreview');
        if (product.image) {
            imagePreview.src = product.image;
            imagePreview.style.display = 'block';
        } else {
            imagePreview.style.display = 'none';
        }

        document.getElementById("editModal").style.display = "block";
    } else {
        alert("Produk tidak ditemukan!");
    }
}

    // Fungsi untuk menutup modal edit produk
    function closeEditModal() {
        document.getElementById("editModal").style.display = "none";
    }

    // Fungsi untuk membuka modal edit stok
    function openEditStockModal(productId) {
        console.log("Edit stok produk dengan ID:", productId);
        document.getElementById("editStockModal").style.display = "block";
    }

    // Fungsi untuk menutup modal edit stok
    function closeEditStockModal() {
        document.getElementById("editStockModal").style.display = "none";
    }

    // Fungsi untuk menghapus produk
    function deleteProduct(productId) {
        if (confirm("Apakah Anda yakin ingin menghapus produk ini?")) {
            window.location.href = `staff-produk?delete_id=${productId}`;
        }
    }

    // Fungsi untuk menampilkan pratinjau gambar setelah upload di modal tambah produk
    document.getElementById('image').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('imagePreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Fungsi untuk menampilkan pratinjau gambar setelah upload di modal edit produk
    document.getElementById('editImage').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('editImagePreview');
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Fungsi untuk mencari produk
function searchProduct() {
    const searchInput = document.querySelector('input[type="text"][placeholder="Cari produk..."]');
    const searchTerm = searchInput.value.trim().toLowerCase(); // Ambil kata kunci dan hilangkan spasi
    const productRows = document.querySelectorAll('tbody tr'); // Ambil semua baris produk

    productRows.forEach(row => {
        const productName = row.querySelector('td:nth-child(2)').textContent.toLowerCase(); // Ambil nama produk
        if (productName.includes(searchTerm)) {
            row.style.display = ''; // Tampilkan baris jika cocok
        } else {
            row.style.display = 'none'; // Sembunyikan baris jika tidak cocok
        }
    });
}

// Tambahkan event listener untuk tombol Enter dan ikon kaca pembesar
document.querySelector('input[type="text"][placeholder="Cari produk..."]').addEventListener('keypress', function(event) {
    if (event.key === 'Enter') {
        searchProduct(); // Jalankan pencarian saat tombol Enter ditekan
    }
});

document.querySelector('.bi-search').addEventListener('click', function() {
    searchProduct(); // Jalankan pencarian saat ikon kaca pembesar diklik
});

// Fungsi untuk membuka modal edit stok
function openEditStockModal(productId) {
    // Ambil data produk berdasarkan ID
    const product = productList.find(p => p.id === productId);

    if (product) {
        // Isi data ke form edit stok
        document.getElementById('editStockProductId').value = product.id;
        document.getElementById('editStockProductStock').value = product.stock;

        // Tampilkan modal
        document.getElementById("editStockModal").style.display = "block";
    } else {
        alert("Produk tidak ditemukan!");
    }
}

// Fungsi untuk menutup modal edit stok
function closeEditStockModal() {
    document.getElementById("editStockModal").style.display = "none";
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