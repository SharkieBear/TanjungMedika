<?php
session_start();

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

// Inisialisasi cart jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $productId = $_POST['product_id'] ?? null;
    $newQuantity = $_POST['quantity'] ?? null;
    
    switch ($_POST['action']) {
        case 'update_quantity':
    if ($productId && $newQuantity) {
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $productId) {
                $oldQuantity = $item['quantity'];
                $quantityDiff = $newQuantity - $oldQuantity;
                
                try {
                    // Update stok di database
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stmt->execute([$quantityDiff, $productId, $quantityDiff]);
                    
                    if ($stmt->rowCount() > 0) {
                        $item['quantity'] = (int)$newQuantity;
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Stok tidak mencukupi']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => 'Gagal mengupdate stok: ' . $e->getMessage()]);
                }
                break;
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }
    exit;
            
        case 'remove_item':
    if ($productId) {
        // Cari item yang akan dihapus untuk mendapatkan quantity-nya
        $removedItem = null;
        foreach ($_SESSION['cart'] as $item) {
            if ($item['id'] == $productId) {
                $removedItem = $item;
                break;
            }
        }
        
        if ($removedItem) {
            try {
                // Update stok di database
                $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$removedItem['quantity'], $productId]);
                
                // Hapus item dari keranjang
                $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($productId) {
                    return $item['id'] != $productId;
                });
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Gagal mengupdate stok: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Produk tidak ditemukan di keranjang']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    }
    exit;
    }
}

// Ambil data cart dari session
$cart = $_SESSION['cart'];

// Fungsi untuk memformat harga ke format IDR
function formatPriceToIDR($price) {
    return "Rp " . number_format($price, 0, ",", ".");
}

// Proses checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($cart)) {
        die("Keranjang belanja kosong.");
    }

    // Pastikan user_id ada di session
    if (!isset($_SESSION['user']['id'])) {
        die("Anda harus login untuk melakukan checkout.");
    }

    // Cek apakah ada produk yang butuh resep (prescription = 1)
    $needPrescription = false;
    foreach ($cart as $item) {
        if ($item['prescription'] == 1) {
            $needPrescription = true;
            break;
        }
    }

    // Hitung total harga
    $cartTotal = 0;
    foreach ($cart as $item) {
        $cartTotal += $item['price'] * $item['quantity'];
    }

    // Format daftar produk yang dipesan
    $products = [];
    foreach ($cart as $item) {
        $products[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'image' => $item['image'],
            'prescription' => $item['prescription']
        ];
    }
    $productsJson = json_encode($products);

    // Simpan data ke tabel customer_orders
    try {
        $stmt = $pdo->prepare("INSERT INTO customer_orders (user_id, total, prescription, pickup_time, status, products, order_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user']['id'],
            $cartTotal,
            $needPrescription ? 0 : 1, // 0 jika butuh resep (belum diupload), 1 jika tidak butuh
            '', 
            'Menunggu Konfirmasi',
            $productsJson,
        ]);

        // Dapatkan ID pesanan yang baru dibuat
        $orderId = $pdo->lastInsertId();

        // Kosongkan keranjang belanja setelah checkout
        $_SESSION['cart'] = [];

        // Redirect berdasarkan kebutuhan resep
        if ($needPrescription) {
            header("Location: prescription.php?order_id=" . $orderId);
        } else {
            header("Location: delivery.php?order_id=" . $orderId);
        }
        exit;
    } catch (PDOException $e) {
        die("Error saat menyimpan pesanan: " . $e->getMessage());
    }
}

// Hitung total harga untuk ditampilkan di halaman
$cartTotal = 0;
foreach ($cart as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
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

        .cart-item {
            display: flex;
            align-items: center;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }

        .cart-item-info {
            flex-grow: 1;
        }

        .cart-item-info h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 10px;
        }

        .cart-item-info p {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-item-actions input {
            width: 60px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5px;
        }

        .cart-total {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .cart-total h2 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 20px;
        }

        .cart-total button {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .cart-total button:hover {
            background-color: #45a049;
        }

        .empty-cart {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .empty-cart p {
            font-size: 18px;
            color: #666;
        }

        .home-button {
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

        .home-button:hover {
            background-color: #45a049;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .quantity-control button {
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .quantity-control button:hover {
            background-color: #0056b3;
        }

        .remove-item {
            background-color: #ff4d4d;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
        }

        .remove-item:hover {
            background-color: #cc0000;
        }
        
        h1 {
            margin-bottom: 20px;
        }
        
        .prescription-notice {
        font-size: 13px !important;
        padding: 5px;
        border-radius: 4px;
        margin: 10px 0;
        display: inline-block;
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }
        
        /* Mobile View (up to 640px) */
@media (max-width: 640px) {
    .container {
        padding: 15px !important;
    }

    .home-button {
        padding: 12px !important;
        font-size: 14px !important;
        margin-bottom: 15px !important;
    }

    h1 {
        font-size: 1.5rem !important;
        margin-bottom: 15px !important;
    }

    .cart-item {
        flex-direction: column;
        padding: 15px !important;
        align-items: flex-start;
    }

    .cart-item img {
        width: 100% !important;
        height: auto !important;
        max-height: 200px;
        margin-right: 0 !important;
        margin-bottom: 15px;
    }

    .cart-item-info {
        width: 100%;
        margin-bottom: 15px;
    }

    .cart-item-info h3 {
        font-size: 1.1rem !important;
    }

    .cart-item-info p {
        font-size: 13px !important;
    }

    .prescription-notice {
        font-size: 13px !important;
        padding: 5px;
        border-radius: 4px;
        margin: 10px 0;
        display: inline-block;
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }

    .cart-item-actions {
        width: 100%;
        justify-content: space-between;
    }

    .quantity-control {
        gap: 3px !important;
    }

    .quantity-control button {
        padding: 8px 12px !important;
        font-size: 14px !important;
    }

    .quantity-control input {
        width: 50px !important;
        padding: 8px !important;
    }

    .remove-item {
        padding: 8px 12px !important;
        font-size: 14px !important;
    }

    .cart-total {
        padding: 15px !important;
    }

    .cart-total h2 {
        font-size: 1.2rem !important;
        margin-bottom: 15px !important;
    }

    .cart-total button {
        padding: 12px !important;
        font-size: 15px !important;
    }

    .empty-cart {
        padding: 30px 15px !important;
    }

    .empty-cart p {
        font-size: 16px !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="home-button"><i class="fas fa-arrow-left"></i></a>
        <h1>Keranjang Belanja</h1>

        <?php if (empty($cart)) : ?>
            <div class="empty-cart">
                <p>Keranjang belanja Anda kosong.</p>
            </div>
        <?php else : ?>
            <?php foreach ($cart as $item) : ?>
                <div class="cart-item" data-product-id="<?php echo $item['id']; ?>">
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="cart-item-info">
                        <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="item-price"><?php echo formatPriceToIDR($item['price']); ?></p>
                        <?php if ($item['prescription'] == 1) : ?>
                            <p class="prescription-notice" style="color: red;">Membutuhkan resep dokter</p>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-actions">
                        <div class="quantity-control">
                            <button class="decrease-quantity">-</button>
                            <input type="number" class="quantity-input" 
                                   value="<?php echo $item['quantity']; ?>" 
                                   min="1" 
                                   max="<?php echo $item['stock']; ?>">
                            <button class="increase-quantity">+</button>
                        </div>
                        <button class="remove-item"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cart-total">
                <h2>Total: <span id="cart-total"><?php echo formatPriceToIDR($cartTotal); ?></span></h2>
                <form method="POST" action="cart.php">
                    <button type="submit" name="checkout">Checkout</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Fungsi untuk update quantity via AJAX
        function updateQuantity(productId, newQuantity) {
            fetch('cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&product_id=${productId}&quantity=${newQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Gagal update quantity');
                }
            });
        }

        // Fungsi untuk hapus item via AJAX
        function removeItem(productId) {
            if (confirm('Apakah Anda yakin ingin menghapus produk ini dari keranjang?')) {
                fetch('cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove_item&product_id=${productId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const itemElement = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                        if (itemElement) {
                            itemElement.remove();
                            updateCartTotal();
                            // Jika keranjang kosong, reload halaman
                            if (document.querySelectorAll('.cart-item').length === 0) {
                                location.reload();
                            }
                        }
                    } else {
                        console.error('Gagal menghapus item');
                    }
                });
            }
        }

        // Fungsi untuk menghitung total harga
        function updateCartTotal() {
            let total = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const priceText = item.querySelector('.item-price').innerText;
                const price = parseFloat(priceText.replace(/[^0-9]/g, ''));
                const quantity = parseInt(item.querySelector('.quantity-input').value);
                total += price * quantity;
            });

            document.getElementById('cart-total').innerText = "Rp " + total.toLocaleString('id-ID');
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update quantity
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    const productId = this.closest('.cart-item').dataset.productId;
                    updateQuantity(productId, this.value);
                    updateCartTotal();
                });
            });

            // Tambah quantity
            document.querySelectorAll('.increase-quantity').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.quantity-control').querySelector('.quantity-input');
                    const max = parseInt(input.getAttribute('max'));
                    if (input.value < max) {
                        input.value = parseInt(input.value) + 1;
                        const productId = this.closest('.cart-item').dataset.productId;
                        updateQuantity(productId, input.value);
                        updateCartTotal();
                    }
                });
            });

            // Kurangi quantity
            document.querySelectorAll('.decrease-quantity').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.quantity-control').querySelector('.quantity-input');
                    if (input.value > 1) {
                        input.value = parseInt(input.value) - 1;
                        const productId = this.closest('.cart-item').dataset.productId;
                        updateQuantity(productId, input.value);
                        updateCartTotal();
                    }
                });
            });

            // Hapus item
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.closest('.cart-item').dataset.productId;
                    removeItem(productId);
                });
            });
        });
    </script>
</body>
</html>