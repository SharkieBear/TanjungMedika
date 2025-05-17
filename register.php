<?php
session_start();

// Konfigurasi koneksi database
$host = 'localhost';
$port = '3308';
$socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$dbname = 'tanjungmedika';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$errorMessage = '';

// Proses registrasi saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Buat koneksi menggunakan socket dan charset
        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ambil data dari form
        $firstName = $_POST['firstName'] ?? '';
        $lastName = $_POST['lastName'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        // Validasi kesesuaian password
        if ($password !== $confirmPassword) {
            $errorMessage = 'Kata sandi dan konfirmasi kata sandi tidak cocok!';
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Cek apakah email sudah terdaftar
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $emailExists = $stmt->fetchColumn();

            if ($emailExists) {
                $errorMessage = 'Email sudah terdaftar!';
            } else {
                // Simpan data pengguna baru
                $stmt = $pdo->prepare('
                    INSERT INTO users (first_name, last_name, email, password)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([$firstName, $lastName, $email, $hashedPassword]);

                // Redirect ke halaman login setelah berhasil registrasi
                header('Location: login');
                exit;
            }
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
    <title>Tanjung TanjungMedika</title>
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
            width: 480px;
            padding: 20px;
            padding-top: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        h2 {
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
        }

        .input-container input:focus {
            border-color: green;
        }

        .input-container input:focus + label,
        .input-container input.has-value + label {
            top: 0;
            font-size: 16px;
            color: green;
            background: white;
            padding: 0 5px;
        }

        button {
            width: 100%;
            padding: 16px;
            background: #39ac6d;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 20px;
            cursor: pointer;
            margin-top: 10px;
            margin-bottom: 18px;
        }

        button:hover {
            background: #2E8B57;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        .login-link {
            margin-top: 10px;
            font-size: 16px;
            margin-bottom: 18px;
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
        color: #39ac6d !important;
    }

    button {
        padding: 15px !important;
        font-size: 16px !important;
        margin: 15px 0 !important;
        border-radius: 8px !important;
    }

    .error {
        font-size: 14px !important;
        margin-bottom: 10px !important;
    }

    .login-link {
        font-size: 14px !important;
        margin: 10px 0 !important;
    }

    .login-link a {
        color: #39ac6d !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <h2>Daftar Sekarang</h2>
        <form method="POST" id="registerForm">
            <div class="input-group">
                <div class="input-container">
                    <input type="text" id="firstName" name="firstName" required value="<?php echo isset($firstName) ? $firstName : ''; ?>">
                    <label for="firstName">Nama Depan</label>
                </div>
            </div>

            <div class="input-group">
                <div class="input-container">
                    <input type="text" id="lastName" name="lastName" required value="<?php echo isset($lastName) ? $lastName : ''; ?>">
                    <label for="lastName">Nama Belakang</label>
                </div>
            </div>

            <div class="input-group">
                <div class="input-container">
                    <input type="email" id="email" name="email" required value="<?php echo isset($email) ? $email : ''; ?>">
                    <label for="email">Email</label>
                </div>
            </div>

            <div class="input-group">
                <div class="input-container">
                    <input type="password" id="password" name="password" required>
                    <label for="password">Kata Sandi</label>
                </div>
            </div>

            <div class="input-group">
                <div class="input-container">
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                    <label for="confirmPassword">Konfirmasi Kata Sandi</label>
                </div>
            </div>

            <?php if ($errorMessage): ?>
                <p id="errorMessage" class="error"><?php echo $errorMessage; ?></p>
            <?php endif; ?>

            <button type="submit" id="registerBtn">Daftar</button>
            <p class="login-link">Sudah punya akun? <a href="login.php" style="color: #2E8B57; text-decoration: none;">Masuk</a></p>
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