<?php
session_start();

// Pastikan pengguna sudah login
if (!isset($_SESSION['user']['id'])) {
    die("Anda harus login untuk mengakses halaman ini.");
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

// Proses upload file resep
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['prescription'])) {
    $uploadDir = 'uploads/prescriptions/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            die("Gagal membuat folder upload.");
        }
    }

    $uploadedFiles = [];
    foreach ($_FILES['prescription']['tmp_name'] as $key => $tmpName) {
        $fileName = basename($_FILES['prescription']['name'][$key]);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $filePath)) {
            $uploadedFiles[] = $fileName;
        } else {
            die("Gagal mengupload file.");
        }
    }

    // Simpan nama file ke session
    $_SESSION['prescription_files'] = $uploadedFiles;

    // Update tabel customer_orders
    $userId = $_SESSION['user']['id'];
    $prescriptionFiles = implode(',', $uploadedFiles); // Gabungkan nama file menjadi string

    try {
        // Update kolom `prescription` ke 1 dan simpan nama file ke `prescription_files`
        $stmt = $pdo->prepare("UPDATE customer_orders SET prescription = 1, prescription_files = ? WHERE user_id = ? AND status = 'Menunggu Konfirmasi'");
        $stmt->execute([$prescriptionFiles, $userId]);
    } catch (PDOException $e) {
        die("Gagal mengupdate database: " . $e->getMessage());
    }

    // Redirect ke delivery
    header("Location: delivery");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Resep - Tanjung Medika</title>
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
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    min-height: 80vh;
    display: flex;
    justify-content: center;
    align-items: center;
}

.container {
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.header h2 {
    font-size: 24px;
    font-weight: 600;
    color: #111827;
    text-align: center;
    flex-grow: 1;
}

.header button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #666;
    transition: color 0.3s;
}

.header button:hover {
    color: #4CAF50;
}

.upload-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 40px;
    background-color: #fff;
    cursor: pointer;
    transition: border-color 0.3s, background-color 0.3s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.upload-box.dragging {
    border-color: #4CAF50;
    background-color: #e8f5e9;
}

.upload-box svg {
    width: 48px;
    height: 48px;
    margin-bottom: 10px;
    color: #666;
}

.upload-box p {
    color: #666;
    font-size: 16px;
    margin-bottom: 10px;
    text-align: center;
}

.upload-box button {
    padding: 10px 20px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s, transform 0.2s;
}

.upload-box button:hover {
    background-color: #45a049;
    transform: translateY(-2px);
}

.uploaded-files {
    margin-top: 20px;
}

.uploaded-files h3 {
    font-size: 18px;
    font-weight: 500;
    color: #111827;
    margin-bottom: 10px;
}

.uploaded-files ul {
    list-style: none;
}

.uploaded-files li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: box-shadow 0.3s;
}

.uploaded-files li:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.uploaded-files li span {
    font-size: 14px;
    color: #333;
}

.uploaded-files li button {
    background: none;
    border: none;
    color: #ff4444;
    cursor: pointer;
    font-size: 16px;
    transition: color 0.3s;
}

.uploaded-files li button:hover {
    color: #cc0000;
}

.navigation-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.navigation-buttons a,
.navigation-buttons button {
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.3s, transform 0.2s;
}

.navigation-buttons a {
    background-color: #e0e0e0;
    color: #333;
}

.navigation-buttons a:hover {
    background-color: #d0d0d0;
    transform: translateY(-2px);
}

.navigation-buttons button {
    background-color: #4CAF50;
    color: white;
    border: none;
}

.navigation-buttons button:hover {
    background-color: #45a049;
    transform: translateY(-2px);
}

.navigation-buttons button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    transform: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }

    .header h2 {
        font-size: 20px;
    }

    .upload-box {
        padding: 20px;
    }

    .upload-box p {
        font-size: 14px;
    }

    .upload-box button {
        padding: 8px 16px;
        font-size: 12px;
    }

    .navigation-buttons a,
    .navigation-buttons button {
        padding: 10px 20px;
        font-size: 12px;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h2>Upload Resep</h2>
            <div style="width: 40px;"></div> <!-- Spacer untuk alignment -->
        </div>

        <!-- Form Upload Resep -->
        <form method="POST" action="prescription" enctype="multipart/form-data">
            <div class="upload-box" id="uploadBox">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V6a2 2 0 012-2h6a2 2 0 012 2v10m4 4H5a2 2 0 01-2-2v-4h18v4a2 2 0 01-2 2z" />
                </svg>
                <p>Drag and drop your prescription here</p>
                <p class="text-sm text-gray-500">or</p>
                <button type="button" onclick="document.getElementById('fileInput').click()" style="margin-bottom: 20px;">Browse Files</button>
                <input id="fileInput" type="file" name="prescription[]" class="hidden" accept="image/*,.pdf" multiple onchange="handleFileSelect(event)">
            </div>

            <!-- Uploaded Files -->
            <div class="uploaded-files" id="uploadedFiles">
                <h3>Uploaded Files</h3>
                <ul id="fileList"></ul>
            </div>

            <!-- Tombol Navigasi -->
            <div class="navigation-buttons">
                <a href="cart.php" class="bg-gray-200 text-gray-600 px-4 py-3 rounded-lg hover:bg-gray-300 font-medium">Back to Cart</a>
                <button type="submit" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition" id="continueButton" <?php echo empty($_SESSION['prescription_files']) ? 'disabled' : ''; ?>>Continue to Delivery</button>
            </div>
        </form>
    </div>

    <script>
        // Fungsi untuk menangani drag and drop
        const uploadBox = document.getElementById('uploadBox');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const continueButton = document.getElementById('continueButton');

        uploadBox.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadBox.classList.add('dragging');
        });

        uploadBox.addEventListener('dragleave', () => {
            uploadBox.classList.remove('dragging');
        });

        uploadBox.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadBox.classList.remove('dragging');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(e);
        });

        // Fungsi untuk menangani file yang dipilih
        function handleFileSelect(event) {
            const files = event.target.files || event.dataTransfer.files;
            fileList.innerHTML = '';

            for (const file of files) {
                const li = document.createElement('li');
                li.innerHTML = `
                    <span>${file.name}</span>
                    <span>${(file.size / 1024).toFixed(1)} KB</span>
                    <button type="button" onclick="removeFile(this)">✖</button>
                `;
                fileList.appendChild(li);
            }

            // Aktifkan tombol "Continue to Delivery"
            if (files.length > 0) {
                continueButton.disabled = false;
            }
        }

        // Fungsi untuk menghapus file
        function removeFile(button) {
            const li = button.parentElement;
            li.remove();

            // Nonaktifkan tombol "Continue to Delivery" jika tidak ada file
            if (fileList.children.length === 0) {
                continueButton.disabled = true;
            }
        }
    </script>
</body>
</html>