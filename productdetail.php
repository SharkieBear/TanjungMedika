<?php
session_start();

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

// Ambil ID produk dari URL
$productId = $_GET['id'] ?? null;

if (!$productId) {
    die("Produk tidak ditemukan.");
}

// Ambil data produk dari database
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Produk tidak ditemukan.");
}

// Inisialisasi cart jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fungsi untuk memformat harga ke format IDR
function formatPriceToIDR($price) {
    return "Rp " . number_format($price, 0, ",", ".");
}

// Proses tambah ke keranjang belanja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = (int)$_POST['quantity'];
    
    // Validasi stok
    if ($quantity > $product['stock']) {
        die("Jumlah melebihi stok yang tersedia.");
    }

    // Mulai transaksi
    $pdo->beginTransaction();
    
    try {
        // Cek apakah produk sudah ada di keranjang
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $productId) {
                // Hitung total quantity yang akan ada di cart
                $newQuantity = $item['quantity'] + $quantity;
                if ($newQuantity > $product['stock']) {
                    throw new Exception("Jumlah melebihi stok yang tersedia.");
                }
                $item['quantity'] = $newQuantity;
                $found = true;
                break;
            }
        }

        // Jika produk belum ada di keranjang, tambahkan ke keranjang
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['harga'],
                'quantity' => $quantity,
                'image' => $product['image'],
                'original_stock' => $product['stock'], // Simpan stok awal
                'prescription' => $product['prescription']
            ];
        }

        // Update stok di database
        $newStock = $product['stock'] - $quantity;
        $updateStmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $updateStmt->execute([$newStock, $productId]);
        
        // Commit transaksi
        $pdo->commit();

        // Redirect ke halaman keranjang belanja
        header("Location: cart.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi error
        $pdo->rollBack();
        die($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Tanjung Medika</title>
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

        /* Tombol Home */
        .home-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px; /* Jarak antara tombol Home dan konten produk */
        }

        .home-button:hover {
            background-color: #45a049;
        }

        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .product-detail img {
            width: 100%;
            height: 450px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e5e7eb; /* Border untuk gambar */
        }

        .product-info h1 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 10px;
        }

        .product-info p {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .product-info .price {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
        }

        .product-info .stock {
            font-size: 16px;
            color: #666;
            margin-bottom: 20px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quantity-control label {
            font-size: 14px;
            color: #666;
        }

        .quantity-control button {
            width: 30px;
            height: 30px;
            border: 1px solid #ccc;
            border-radius: 50%;
            background-color: white;
            cursor: pointer;
        }

        .quantity-control input {
            width: 60px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5px;
        }

        .add-to-cart {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .add-to-cart:hover {
            background-color: #45a049;
        }

        .description {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            grid-column: span 2; /* Deskripsi mengambil 2 kolom */
        }

        .description h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 10px; /* Jarak antara judul dan isi deskripsi */
        }

        .description p {
            line-height: 1.6; /* Jarak antar baris untuk deskripsi */
        }
        
        hr {
            margin-bottom: 20px;
        }
        
        .price {
            padding-top: 10px;
            padding-bottom: 10px;
        }
        
        .prescription-info {
        font-size: 13px !important;
        padding: 5px;
        border-radius: 4px;
        margin: 10px 0;
        display: inline-block;
    }

    .prescription-required {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }

    .prescription-not-required {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }
        
        /* Mobile View (up to 640px) */
@media (max-width: 640px) and (max-width: 1920px) {
    .container {
        padding: 15px !important;
    }

    .home-button {
        padding: 12px !important;
        margin-bottom: 15px !important;
    }

    .product-detail {
        grid-template-columns: 1fr !important;
        padding: 15px !important;
        gap: 15px !important;
    }

    .product-detail img {
        height: 300px !important;
        max-width: 100%;
        margin-bottom: 15px;
    }

    .product-info h1 {
        font-size: 1.4rem !important;
        margin-bottom: 8px !important;
    }

    .product-info p {
        font-size: 13px !important;
        margin-bottom: 8px !important;
    }

    .price {
        font-size: 1.2rem !important;
        padding: 8px 0 !important;
    }

    .stock {
        font-size: 14px !important;
    }

    .quantity-control {
        margin: 15px 0 !important;
    }

    .quantity-control input {
        width: 50px !important;
    }

    .add-to-cart {
        padding: 12px !important;
        font-size: 15px !important;
    }

    .description {
        grid-column: span 1 !important;
        margin-top: 15px !important;
        font-size: 13px !important;
    }

    .description h3 {
        font-size: 1.1rem !important;
    }

    .prescription-info {
        font-size: 13px !important;
        padding: 5px;
        border-radius: 4px;
        margin: 10px 0;
        display: inline-block;
    }

    .prescription-required {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }

    .prescription-not-required {
        background-color: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }

    hr {
        margin: 15px 0 !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Tombol Home -->
        <a href="index.php" class="home-button"><i class="fas fa-arrow-left"></i></a>


        <!-- Detail Produk -->
        <div class="product-detail">
            <div class="product-image">
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            <div class="product-info">
                <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                <p>Kategori: <?php echo htmlspecialchars($product['category']); ?></p>
                <p>Golongan: <?php echo htmlspecialchars($product['golongan']); ?></p>
                
                <!-- Info Resep -->
                <div class="prescription-info <?php echo $product['prescription'] ? 'prescription-required' : 'prescription-not-required'; ?>">
                    <?php echo $product['prescription'] ? 'Membutuhkan Resep Dokter' : 'Tidak Membutuhkan Resep (Over the Counter)'; ?>
                </div>
                
                <div class="price"><?php echo formatPriceToIDR($product['harga']); ?></div>
                <hr>
                <p>Stok:</p>
                <div class="stock"><?php echo $product['stock']; ?> pcs available</div>
                <hr>
                <form method="POST" action="">
                    <div class="quantity-control">
                        <label for="quantity">Jumlah:</label>
                        <button type="button" onclick="decrementQuantity()">-</button>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                        <button type="button" onclick="incrementQuantity()">+</button>
                    </div>
                    <button type="submit" name="add_to_cart" class="add-to-cart">
                        <i class="bi bi-cart-plus"></i> Tambah ke Keranjang
                    </button>
                </form>
            </div>
            <div class="description">
                <h3>Deskripsi Produk</h3>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
        </div>
    </div>

    <script>
        function incrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            let quantity = parseInt(quantityInput.value);
            const maxStock = <?php echo $product['stock']; ?>;
            
            if (quantity < maxStock) {
                quantityInput.value = quantity + 1;
            } else {
                alert('Jumlah tidak boleh melebihi stok yang tersedia: ' + maxStock);
            }
        }

        function decrementQuantity() {
            const quantityInput = document.getElementById('quantity');
            let quantity = parseInt(quantityInput.value);
            
            if (quantity > 1) {
                quantityInput.value = quantity - 1;
            }
        }

        // Validasi input manual
        document.getElementById('quantity').addEventListener('change', function() {
            const maxStock = <?php echo $product['stock']; ?>;
            if (this.value > maxStock) {
                alert('Jumlah tidak boleh melebihi stok yang tersedia: ' + maxStock);
                this.value = maxStock;
            } else if (this.value < 1) {
                this.value = 1;
            }
        });
    </script>
</body>
</html>