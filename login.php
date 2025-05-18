<?php
session_start();

echo '<pre>';
var_dump($_SESSION);
echo '</pre>';

// Konfigurasi koneksi database
$host = 'localhost';
$port = '3308'; // Port MySQL XAMPP jika bukan default 3306
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$dbname = 'tanjungmedika';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$errorMessage = '';

// Proses login ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // Buat koneksi PDO dengan socket dan charset
        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ambil data user berdasarkan email
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Simpan user ke dalam session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                ];
                header('Location: index'); // Redirect setelah login berhasil
                exit;
            } else {
                $errorMessage = 'Kata sandi salah!';
            }
        } else {
            $errorMessage = 'Email tidak ditemukan!';
        }
    } catch (PDOException $e) {
        $errorMessage = 'Koneksi atau query gagal: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
    <link rel="icon" href="images/Logo.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: url('/images/pharmacy.jpg') no-repeat center center/cover;
        }

        .container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h2 {
            margin-top: 20px;
            font-size: 24px;
            color: #333;
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .input-container {
            position: relative;
            width: 100%;
        }

        .input-container input {
            width: 100%;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            outline: none;
            background: transparent;
            transition: border-color 0.1s ease-in-out;
            margin-bottom: 18px;
        }

        .input-container input:focus {
            border-color: #39ac6d;
        }

        .input-container label {
            position: absolute;
            left: 10px;
            top: 35%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #999;
            background: white;
            padding: 0 5px;
            transition: all 0.1s ease-in-out;
            pointer-events: none;
        }

        .input-container input:focus + label,
        .input-container input.has-value + label {
            top: 0;
            font-size: 16px;
            color: #39ac6d;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        button {
            width: 100%;
            padding: 16px;
            background: #39ac6d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s ease-in-out;
            margin-bottom: 18px;
        }

        button:hover {
            background: #2E8B57;
        }

        .forgot-password {
            margin-top: 10px;
            text-align: left;
            margin-bottom: 18px;
            margin-left: 5px;
        }

        .forgot-password a {
            color: #39ac6d;
            text-decoration: none;
            font-size: 16px;
            margin-bottom: 18px;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .register-link {
            margin-top: 15px;
            font-size: 16px;
            color: #666;
            margin-bottom: 18px;
        }

        .register-link a {
            color: #39ac6d;
            text-decoration: none;
            margin-bottom: 18px;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
        
        /* Mobile View (up to 640px) */
@media (max-width: 640px) {
    body {
        padding: 20px;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        min-height: 100vh;
        background-attachment: fixed;
    }

    .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 25px 20px !important;
        margin: 0 !important;
        border-radius: 10px !important;
        box-shadow: 0 2px 15px rgba(0,0,0,0.2) !important;
        background-color: rgba(255, 255, 255, 0.95);
    }

    h2 {
        font-size: 1.5rem !important;
        margin: 10px 0 25px 0 !important;
    }

    .input-container input {
        padding: 16px 15px !important;
        font-size: 15px !important;
        margin-bottom: 15px !important;
    }

    .input-container label {
        font-size: 15px !important;
        transform: translateY(-50%) !important;
    }

    .input-container input:focus + label,
    .input-container input.has-value + label {
        top: 0 !important;
        font-size: 13px !important;
        transform: translateY(0) !important;
    }

    button {
        padding: 15px !important;
        font-size: 16px !important;
        margin: 15px 0 !important;
    }

    .error {
        font-size: 14px !important;
        margin-bottom: 10px !important;
    }

    .forgot-password a,
    .register-link,
    .register-link a {
        font-size: 14px !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <h2>Masuk Akun Anda</h2>
        <form method="POST" action="login.php">
            <!-- Email -->
            <div class="input-group">
                <div class="input-container">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="<?php echo isset($_POST['email']) ? 'has-value' : ''; ?>"
                    />
                    <label for="email">Email</label>
                </div>
            </div>

            <!-- Kata Sandi -->
            <div class="input-group">
                <div class="input-container">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="<?php echo isset($_POST['password']) ? 'has-value' : ''; ?>"
                    />
                    <label for="password">Kata Sandi</label>
                </div>
            </div>

            <!-- Pesan Error -->
            <?php if ($errorMessage): ?>
                <p class="error"><?php echo $errorMessage; ?></p>
            <?php endif; ?>

            <!-- Tombol Masuk -->
            <button type="submit">Masuk ke Akun Anda</button>

            <!-- Link Registrasi -->
            <div class="register-link">
                Belum punya akun? <a href="register.php">Buat akun</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const inputs = document.querySelectorAll(".input-container input");

            inputs.forEach(input => {
                function checkValue() {
                    if (input.value.trim()) {
                        input.classList.add("has-value");
                    } else {
                        input.classList.remove("has-value");
                    }
                }

                // Cek nilai saat halaman dimuat
                checkValue();

                // Tambahkan event listener untuk input dan blur
                input.addEventListener("input", checkValue);
                input.addEventListener("blur", checkValue);
            });
        });
    </script>
</body>
</html>