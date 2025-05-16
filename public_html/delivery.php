<?php
session_start();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user']['id'])) {
    die("Anda harus login untuk mengakses halaman ini.");
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

// Proses pemilihan waktu pengambilan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_time'])) {
    $pickupTime = $_POST['selected_time'];
    $userId = $_SESSION['user']['id'];

    // Simpan waktu pengambilan ke tabel customer_orders
    try {
        // Pastikan Anda memiliki order_id atau cara lain untuk mengidentifikasi pesanan yang sedang diproses
        // Contoh: Ambil order_id terakhir untuk user ini, atau gunakan session untuk menyimpan order_id
        $stmt = $pdo->prepare("UPDATE customer_orders SET pickup_time = :pickup_time WHERE user_id = :user_id AND status = 'Menunggu Konfirmasi'");
        $stmt->execute([
            ':pickup_time' => $pickupTime,
            ':user_id' => $userId
        ]);

        $_SESSION['pickup_time'] = $pickupTime;

        // Redirect ke halaman konfirmasi
        header("Location: confirmation");
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Waktu Pengambilan - Tanjung Medika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
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

        .delivery-options {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .delivery-options h2 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
        }

        .store-pickup {
            width: 100%;
            padding: 20px;
            border: 2px solid #4CAF50;
            border-radius: 12px;
            background-color: #e8f5e9;
            text-align: center;
            margin-bottom: 20px;
        }

        .store-pickup h3 {
            font-size: 18px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 10px;
        }

        .store-pickup p {
            font-size: 14px;
            color: #666;
        }

        .time-selection {
            margin-bottom: 20px;
        }

        .time-selection label {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            margin-bottom: 10px;
            display: block;
        }

        .time-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .time-buttons button {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            cursor: pointer;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .time-buttons button.selected {
            border-color: #4CAF50;
            background-color: #e8f5e9;
        }

        .time-buttons button:hover {
            background-color: #f0f0f0;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .navigation-buttons button {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .navigation-buttons button.back {
            background-color: #e0e0e0;
            color: #111827;
        }

        .navigation-buttons button.back:hover {
            background-color: #d0d0d0;
        }

        .navigation-buttons button.continue {
            background-color: #4CAF50;
            color: white;
        }

        .navigation-buttons button.continue:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .navigation-buttons button.continue:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="delivery-options">
            <h2>Pilih Waktu Pengambilan</h2>

            <!-- Store Pickup Button -->
            <div class="store-pickup">
                <h3>Ambil di Toko</h3>
                <p>Ambil pesanan Anda sesuai kenyamanan Anda</p>
            </div>

            <!-- Time Selection -->
            <form method="POST" action="delivery">
                <div class="time-selection">
                    <label>Pilih Waktu Pengambilan</label>
                    <div class="time-buttons">
                        <?php
                        $availableTimes = ["9:00 AM - 11:00 AM", "11:00 AM - 1:00 PM", "2:00 PM - 4:00 PM", "4:00 PM - 6:00 PM"];
                        foreach ($availableTimes as $time) {
                            echo "<button type='button' onclick='selectTime(\"$time\")' class='time-button'>$time</button>";
                        }
                        ?>
                    </div>
                    <input type="hidden" name="selected_time" id="selectedTime">
                </div>

                <!-- Tombol Navigasi -->
                <div class="navigation-buttons">
                    <button type="button" onclick="window.history.back()" class="back">Kembali</button>
                    <button type="submit" class="continue" id="continueButton" disabled>Lanjut ke Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Fungsi untuk memilih waktu
        function selectTime(time) {
            const buttons = document.querySelectorAll('.time-button');
            buttons.forEach(button => {
                button.classList.remove('selected');
                if (button.textContent === time) {
                    button.classList.add('selected');
                }
            });

            document.getElementById('selectedTime').value = time;
            document.getElementById('continueButton').disabled = false;
        }
    </script>
</body>
</html>