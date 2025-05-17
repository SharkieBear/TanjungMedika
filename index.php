<?php

// Atur parameter cookie session sebelum session_start()
ini_set('session.cookie_lifetime', 86400); // 1 hari
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);


session_start();

echo '<pre>'; 
//var_dump($_SESSION);
echo '</pre>';

// Prevent caching of authenticated pages
if (isset($_SESSION['user'])) {
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
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

$stmt = $pdo->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk memformat harga ke format IDR
function formatPriceToIDR($price) {
    return "Rp " . number_format($price, 0, ",", ".");
}

// Ambil data profil pengguna dari database
// Ambil data profil pengguna dari database
if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id']; // Ambil user_id dari session
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Jika user belum login, set $userProfile ke null atau array kosong
    $userProfile = null;
}

// Di bagian query notifikasi, tambahkan logika untuk mengirim push notification
if (!empty($notifications)) {
    foreach ($notifications as $notification) {
        // Cek jika notifikasi baru
        if ($notification['is_new']) {
            // Kirim push notification
            $title = "Status Pesanan Diperbarui";
            $message = "Pesanan #".$notification['id']." status: ".$notification['status'];
            $url = "OrderHistoryDetail?id=".$notification['id'];
            
            // Gunakan AJAX untuk memanggil send-notification.php
            echo "<script>
                fetch('/send-notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'user_id=".$userId."&title=".urlencode($title)."&message=".urlencode($message)."&url=".urlencode($url)."'
                });
            </script>";
            
            // Tandai notifikasi sebagai sudah dibaca
            $stmt = $pdo->prepare("UPDATE customer_orders SET is_new = 0 WHERE id = ?");
            $stmt->execute([$notification['id']]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="images/Logo.png" type="image/png">
    <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#16a34a">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/images/Logopwa.png">
  <meta name="msapplication-TileImage" content="/images/Logopwa.png">
        
  
  
  <script>
    if ('serviceWorker' in navigator) {
        // Delay registration until page is fully loaded
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('js/sw.js', {
                scope: '/'
            }).then(registration => {
                console.log('ServiceWorker registration successful');
                
                // Force update if user is logged in
                if (<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>) {
                    registration.update();
                }
            }).catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }

    // Clear cache on logout
    function clearCacheOnLogout() {
        if ('serviceWorker' in navigator) {
            caches.keys().then(cacheNames => {
                cacheNames.forEach(cacheName => {
                    caches.delete(cacheName);
                });
            });
        }
    }
</script>
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
            padding: 0px;
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
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header .logo img {
            height: 50px;
        }

        .header .search-bar {
            flex-grow: 1;
            max-width: 600px;
            margin: 0 20px;
            position: relative;
        }

        .header .search-bar input {
            width: 100%;
            padding: 8px 12px 8px 40px; /* Padding kiri untuk ikon */
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
        }

        .header .search-bar .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 16px;
            cursor: pointer;
        }

        .header .cart-button {
            color: #666;
            font-size: 20px;
            transition: color 0.2s ease;
        }

        .header .cart-button:hover {
            color: #16a34a;
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

        .header .user-menu .relative {
            position: relative; /* Elemen induk harus relative */
        }

        .header .user-menu .dropdown {
            position: absolute;
            top: 45px; /* Jarak dari atas elemen induk */
            right: -3; /* Posisi di sebelah kanan elemen induk */
            background-color: white;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: none; /* Sembunyikan dropdown secara default */
            z-index: 1000; /* Pastikan dropdown muncul di atas elemen lain */
            width: 120px;
        }

        .header .user-menu .dropdown a {
            display: block;
            padding: 8px 16px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .header .user-menu .dropdown a:hover {
            background-color: #f3f4f6;
        }

        /* Filter Button Styles */
.filter-buttons {
    display: flex;
    gap: 10px;
    margin-top: 100px;
    margin-bottom: 20px;
    padding: 0 20px 12px 20px;
    overflow-x: auto;
    width: 100%;
    box-sizing: border-box;
    justify-content: center;
    scrollbar-width: thin; /* Untuk Firefox */
    scrollbar-color: #16a34a #f3f4f6; /* Untuk Firefox */
}

/* Scrollbar untuk Chrome/Safari */
.filter-buttons::-webkit-scrollbar {
    height: 10px;
}

.filter-buttons::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 10px;
    margin-top: 10px;
}

.filter-buttons::-webkit-scrollbar-thumb {
    background-color: #16a34a;
    border-radius: 10px;
}

.filter-buttons button {
    padding: 12px 16px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background-color: white;
    color: #333;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
    white-space: nowrap;
    flex-shrink: 0; /* Prevent buttons from shrinking */
    font-size: 14px;
}

        .filter-buttons button.active {
            background-color: #16a34a;
            color: white;
            border-color: #16a34a;
        }

        .filter-buttons button:hover {
            background-color: #16a34a;
            color: white;
        }

        /* Product Grid Styles */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 0 20px;
        }

        .product-card {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .product-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .product-card h5 {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin: 8px;
            line-height: 1.2;
        }

        .product-card .price {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            margin: 8px;
        }
        .vertical-line {
            width: 1px; /* Ketebalan garis */
            height: 40px; /* Panjang garis */
            background-color: grey; /* Warna garis */
            margin-left: 20px;
            margin-right: 10px;
        }
        
        /* Styling untuk tombol Masuk */
.text-green-500 {
    color: #16a34a; /* Warna hijau */
    text-decoration: none; /* Hilangkan underline */
    font-size: 14px; /* Ukuran font */
    font-weight: 500; /* Ketebalan font */
    padding: 8px 16px; /* Padding untuk tombol */
    border: 2px solid #16a34a; /* Border warna hijau */
    border-radius: 8px; /* Sudut tombol */
    transition: background-color 0.2s ease, color 0.2s ease; /* Efek transisi */
}

.text-green-500:hover {
    background-color: #f0fdf4; /* Warna latar belakang saat hover */
    color: #15803d; /* Warna teks saat hover */
}

/* Styling untuk tombol Daftar */
.bg-green-500 {
    background-color: #16a34a; /* Warna hijau */
    color: white; /* Warna teks */
    text-decoration: none; /* Hilangkan underline */
    font-size: 14px; /* Ukuran font */
    font-weight: 500; /* Ketebalan font */
    padding: 8px 16px; /* Padding untuk tombol */
    border-radius: 8px; /* Sudut tombol */
    transition: background-color 0.2s ease, color 0.2s ease; /* Efek transisi */
}

.bg-green-500:hover {
    background-color: #15803d; /* Warna hijau lebih gelap saat hover */
}

        /* Responsive Styles */
        @media (min-width: 640px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .filter-buttons button {
                font-size: 14px; /* Ukuran untuk layar sedang */
            }
        }

        @media (min-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            .filter-buttons {
        scrollbar-width: none; /* Firefox */
    }
    .filter-buttons::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }
        }

        @media (min-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(6, 1fr);
            }
            .filter-buttons button {
                font-size: 16px; /* Ukuran untuk layar besar */
            }
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

    /* Notification System */
    .notification-container {
        position: relative;
        margin-right: 15px;
    }

    .notification-icon {
        position: relative;
        cursor: pointer;
        color: #6b7280; /* gray-500 */
        font-size: 1.25rem; /* 20px */
        transition: color 0.2s ease;
    }

    .notification-icon:hover {
        color: #16a34a; /* green-600 */
    }

    .notification-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #ef4444; /* red-500 */
        border: 2px solid white;
    }

    #notificationDropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb; /* gray-200 */
        display: none;
        z-index: 50;
        margin-top: 0.5rem;
    }

    #notificationDropdown.show {
        display: block;
    }

    .notification-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e5e7eb; /* gray-200 */
        font-weight: 600;
        color: #111827; /* gray-900 */
    }

    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e5e7eb; /* gray-200 */
        transition: background-color 0.2s;
    }

    .notification-item:hover {
        background-color: #f9fafb; /* gray-50 */
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item a {
        display: block;
        text-decoration: none;
        color: inherit;
    }

    .notification-title {
        font-weight: 500;
        color: #111827; /* gray-900 */
        margin-bottom: 0.25rem;
    }

    .notification-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-time {
        font-size: 0.75rem; /* 12px */
        color: #6b7280; /* gray-500 */
    }

    .notification-status {
        display: inline-flex;
        align-items: center;
        font-size: 0.75rem; /* 12px */
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem; /* 6px */
    }

    .status-diproses {
        background-color: #dbeafe; /* blue-100 */
        color: #1e40af; /* blue-800 */
    }

    
    .status-Siap-Diambil {
    background-color: #fef3c7; /* amber-100 */
    color: #92400e; /* amber-800 */
}

.status-dibatalkan {
    background-color: #fee2e2; /* red-100 */
    color: #991b1b; /* red-800 */
}

    .no-notifications {
        padding: 1.5rem;
        text-align: center;
        color: #6b7280; /* gray-500 */
        font-size: 0.875rem; /* 14px */
    }

    @media (max-width: 640px) {
        #notificationDropdown {
            width: 280px;
            right: -20px;
        }
    }
    
    /* Mobile View (up to 640px) */
@media (max-width: 640px) and (max-width: 1920px) {
    /* Header Styles */
    .header {
        padding: 8px 10px !important;
    }

    .header .container {
        display: grid;
        grid-template-columns: auto 1fr; /* 2 kolom - logo dan menu */
        align-items: center;
        gap: 10px;
    }

    /* Logo - Tetap menggunakan Logo2.png */
    .logo {
        grid-column: 1;
    }
    
    .logo img {
        height: 40px !important; /* Ukuran lebih kecil untuk mobile */
    }

    /* Search Bar - Pindah ke baris baru */
    .search-bar {
        grid-column: 1 / span 2; /* Ambil 2 kolom penuh */
        order: 3;
        margin: 0px 0 0 0 !important;
        max-width: 100% !important;
    }

    /* User Menu */
    .user-menu {
        grid-column: 2;
        justify-content: flex-end;
        gap: 8px;
    }

    /* Cart Button */
    .cart-button {
        font-size: 18px !important;
    }

    /* Vertical Line - Tampilkan dengan tinggi lebih pendek */
    .vertical-line {
        height: 30px;
        margin: 0 8px;
    }

    /* Tombol Masuk/Daftar */
    .text-green-500, .bg-green-500 {
        padding: 6px 12px !important;
        font-size: 13px !important;
    }

    /* Notification Icon */
    .notification-container {
        margin-right: 8px !important;
    }

    /* Profile Image */
    .user-menu img {
        width: 32px !important;
        height: 32px !important;
    }

    /* Dropdown Menu */
    .dropdown {
        width: 120px !important;
        right: 0 !important;
        top: 40px !important;
    }

    /* Notification Dropdown */
    #notificationDropdown {
        width: 280px !important;
        right: -80px !important;
    }
    /* Tambahkan ini untuk filter buttons */
    .filter-buttons {
        margin-top: 120px; /* Sesuaikan dengan tinggi header */
        justify-content: flex-start; /* Align left di mobile */
        padding-left: 15px; /* Beri sedikit padding kiri */
        padding-right: 15px;
    }
}

/* Tambahan untuk tampilan yang lebih baik di mobile kecil (<= 400px) */
@media (max-width: 400px) {
    .user-menu {
        gap: 5px;
    }
    
    .text-green-500, .bg-green-500 {
        padding: 5px 8px !important;
        font-size: 12px !important;
    }
    
    .vertical-line {
        margin: 0 5px;
    }
    
    .notification-container {
        margin-right: 5px !important;
    }
}

/* Untuk layar kecil (mobile dan web minimized) */
@media (max-width: 1024px) {
    .filter-buttons {
        justify-content: flex-start; /* Align left */
        margin-top: 115px;
        padding: 0 15px 12px 15px;
    }
    
    .filter-buttons button {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    /* Perbaikan scrollbar untuk mobile */
    .filter-buttons::-webkit-scrollbar {
        height: 6px;
    }
}
    </style>

</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container flex items-center justify-between">
            <!-- Logo -->
            <div class="logo">
                <img src="images/Logo2.png" alt="Logo TanjungMedika">
            </div>

            <!-- Search Bar -->
            <div class="search-bar">
                <i class="bi bi-search search-icon" onclick="searchProducts()"></i>
                <input type="text" id="searchInput" placeholder="Cari produk" onkeypress="handleKeyPress(event)">
            </div>

            <!-- User Menu -->
            <div class="user-menu">
                <?php if (isset($_SESSION['user'])): ?>
                    <!-- Jika sudah login -->
                    <?php
                    // Query untuk mendapatkan notifikasi pesanan
$userId = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT * FROM customer_orders WHERE user_id = ? AND (status = 'Diproses' OR status = 'Dibatalkan' OR status = 'Siap Diambil') ORDER BY order_date DESC LIMIT 5");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$notificationCount = count($notifications);
                    ?>
                    
                    <!-- Notification Icon (in the user-menu section) -->
<div class="notification-container">
    <div class="notification-icon" onclick="toggleNotificationDropdown()">
        <i class="bi bi-bell"></i>
        <?php if ($notificationCount > 0): ?>
            <span class="notification-badge"></span>
        <?php endif; ?>
    </div>
    <div id="notificationDropdown">
    <div class="notification-header">Notifikasi</div>
    <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $notification): ?>
            <div class="notification-item">
                <a href="OrderHistoryDetail?id=<?php echo $notification['id']; ?>">
                    <div class="notification-title">Pesanan #<?php echo $notification['id']; ?></div>
                    <div class="notification-content">
                        <?php 
                        $statusClass = '';
                        switch($notification['status']) {
                            case 'Diproses':
                                $statusClass = 'status-diproses';
                                break;
                            case 'Siap Diambil':
                                $statusClass = 'status-Siap-Diambil';
                                break;
                            case 'Dibatalkan':
                                $statusClass = 'status-dibatalkan';
                                break;
                        }
                        ?>
                        <span class="notification-status <?php echo $statusClass; ?>">
                            <?php echo $notification['status']; ?>
                        </span>
                        <span class="notification-time">
                            <?php echo date("d M Y H:i", strtotime($notification['order_date'])); ?>
                        </span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-notifications">Tidak ada notifikasi</div>
    <?php endif; ?>
</div>
</div>
                    
                    <!-- Cart Icon -->
                    <div class="cart-button">
                        <a href="cart.php">
                            <i class="bi bi-cart2" style="color: grey;"></i>
                        </a>
                    </div>
                    <div class="vertical-line"></div>
                    <div class="relative">
                        <img src="<?php echo $userProfile['avatar'] ?? 'images/profile.jpg'; ?>" alt="Foto Profil" onclick="toggleDropdown()">
                        <div class="dropdown" id="dropdownMenu">
                            <a href="profile.php">Profil Saya</a>
                            <a href="logout.php">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Jika belum login -->
                    <div class="cart-button">
                        <a href="login.php">
                            <i class="bi bi-cart2" style="color: grey;"></i>
                        </a>
                    </div>
                    <div class="vertical-line"></div>
                    <a href="login.php" class="text-green-500">Masuk</a>
                    <a href="register.php" class="bg-green-500 text-white px-4 py-2 rounded-lg">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="filter-buttons">
    <button class="active" data-category="">Semua</button>
    <?php foreach ($categories as $category): ?>
        <button data-category="<?php echo $category['name']; ?>">
            <?php echo $category['name']; ?>
        </button>
    <?php endforeach; ?>
</div>

    <!-- Product Grid -->
    <div class="container">
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card" data-category="<?php echo $product['category']; ?>">
    <a href="productdetail.php?id=<?php echo $product['id']; ?>">
    <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
</a>
    <h5><?php echo $product['name']; ?></h5>
    <div class="price"><?php echo formatPriceToIDR($product['harga']); ?></div>
</div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // JavaScript untuk filter produk
        const filterButtons = document.querySelectorAll(".filter-buttons button");
const productCards = document.querySelectorAll(".product-card");

filterButtons.forEach(button => {
    button.addEventListener("click", () => {
        // Hapus class active dari semua tombol
        filterButtons.forEach(btn => btn.classList.remove("active"));
        // Tambahkan class active ke tombol yang diklik
        button.classList.add("active");

        // Ambil kategori yang dipilih
        const selectedCategory = button.getAttribute("data-category");

        // Filter produk
        productCards.forEach(card => {
            const cardCategory = card.getAttribute("data-category");
            if (selectedCategory === "" || cardCategory === selectedCategory) {
                card.style.display = "block";
            } else {
                card.style.display = "none";
            }
        });
    });
});
        // JavaScript untuk dropdown menu
        function toggleDropdown() {
            const dropdownMenu = document.getElementById("dropdownMenu");
            dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
        }

        // Menutup dropdown saat klik di luar
        document.addEventListener("click", function (event) {
            const dropdownMenu = document.getElementById("dropdownMenu");
            const profileImage = document.querySelector(".user-menu img");

            if (!event.target.closest(".relative") && !event.target.closest(".dropdown")) {
                dropdownMenu.style.display = "none";
            }
        });

        // Fungsi untuk memfilter produk berdasarkan nama
        function searchProducts() {
            const searchQuery = document.getElementById("searchInput").value.toLowerCase();
            const productCards = document.querySelectorAll(".product-card");

            productCards.forEach(card => {
                const productName = card.querySelector("h5").textContent.toLowerCase();
                if (productName.includes(searchQuery)) {
                    card.style.display = "block";
                } else {
                    card.style.display = "none";
                }
            });
        }

        // Fungsi untuk menangani tombol Enter
        function handleKeyPress(event) {
            if (event.key === "Enter") {
                searchProducts();
            }
        }
        
        // Function to toggle notification dropdown
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            
            // Close other dropdowns
            const otherDropdown = document.getElementById('dropdownMenu');
            if (otherDropdown.style.display === 'block') {
                otherDropdown.style.display = 'none';
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationIcon = document.querySelector('.bi-bell');
            
            // Close notification dropdown if clicked outside
            if (!event.target.closest('.relative') && !event.target.closest('.notification-dropdown') && !event.target.closest('.bi-bell')) {
                notificationDropdown.classList.remove('show');
            }
            
            // Close profile dropdown if clicked outside
            const dropdownMenu = document.getElementById('dropdownMenu');
            const profileImage = document.querySelector('.user-menu img');
            if (!event.target.closest('.relative') && !event.target.closest('.dropdown')) {
                dropdownMenu.style.display = 'none';
            }
        });
    </script>
</body>
</html>