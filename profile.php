<?php
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
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

// Ambil data profil pengguna dari database
$userId = $_SESSION['user']['id']; // Asumsikan session menyimpan ID pengguna
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika form disubmit (simpan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dateOfBirth = $_POST['dateOfBirth'];
    $address = $_POST['address'];
    $gender = $_POST['gender'];

    // Handle upload foto profil
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        $targetFile = $targetDir . basename($_FILES['avatar']['name']);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Validasi file gambar
        $check = getimagesize($_FILES['avatar']['tmp_name']);
        if ($check !== false && in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                // Update path foto profil di database
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$targetFile, $userId]);
                $userProfile['avatar'] = $targetFile; // Update session dengan path baru
            }
        }
    }

    // Update data di database
    $stmt = $pdo->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, date_of_birth = ?, address = ?, gender = ?
        WHERE id = ?
    ");
    $stmt->execute([$firstName, $lastName, $email, $phone, $dateOfBirth, $address, $gender, $userId]);

    // Redirect ke halaman profil setelah update
    header("Location: profile.php");
    exit;
}

// Ambil data profil pengguna dari database
$userId = $_SESSION['user']['id']; // Asumsikan session menyimpan ID pengguna
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userProfile = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika form disubmit (simpan perubahan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle upload foto profil
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/"; // Direktori untuk menyimpan foto
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true); // Buat direktori jika belum ada
        }

        $targetFile = $targetDir . basename($_FILES['avatar']['name']);
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Validasi file gambar
        $check = getimagesize($_FILES['avatar']['tmp_name']);
        if ($check !== false && in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                // Update path foto profil di database
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$targetFile, $userId]);
                $userProfile['avatar'] = $targetFile; // Update session dengan path baru
            }
        }
    }
}
// Mode edit (jika tombol "Edit Profile" diklik)
$isEditing = isset($_GET['edit']) && $_GET['edit'] === 'true';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanjung Medika</title>
    <link rel="icon" href="images/Logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    /* Base Styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
    }

    body {
        background-image: url('images/pharmacy.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        background-color: #000;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 10px;
    }

    .container {
        width: 100%;
        max-width: 1000px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        padding: 20px;
        margin: 10px 0 20px;
    }

    /* Header Styles */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .header h2 {
        font-size: 24px;
        color: #333;
        order: 2;
        flex-grow: 1;
        text-align: center;
    }

    .header .actions {
        display: flex;
        gap: 12px;
    }

    .header .actions:first-child {
        order: 1;
    }

    .header .actions:last-child {
        order: 3;
    }

    .header .actions a {
        text-decoration: none;
        padding: 10px 18px;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .header .actions a.home {
        background: #39ac6d;
        color: white;
    }

    .header .actions a.edit {
        background: #007bff;
        color: white;
    }

    .header .actions a:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .header .actions a i {
        font-size: 16px;
    }

    /* Profile Content */
    .profile-content {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-direction: column;
    }

    .profile-summary {
        flex: 1;
        background: #f9f9f9;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 20px;
        flex-direction: column;
        text-align: center;
    }

    .profile-summary img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 2px solid <?php echo ($userProfile['avatar'] ?? 'images/profile.jpg') === 'images/profile.jpg' ? '#007bff' : 'transparent'; ?>;
    }

    .profile-summary .info {
        flex: 1;
    }

    .profile-summary h3 {
        font-size: 20px;
        color: #333;
        margin-bottom: 5px;
    }

    .profile-summary p {
        font-size: 14px;
        color: #666;
    }

    /* Quick Actions */
    .quick-actions {
        flex: 1;
        background: #f9f9f9;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .quick-actions h3 {
        font-size: 18px;
        color: #333;
        margin-bottom: 10px;
    }

    .quick-actions a {
        display: block;
        text-decoration: none;
        color: #007bff;
        margin-bottom: 8px;
        padding: 10px;
        border-radius: 8px;
        transition: background 0.3s ease;
        font-size: 15px;
    }

    .quick-actions a:hover {
        background: #e0f7e0;
    }

    /* Personal Info */
    .personal-info {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .personal-info h3 {
        font-size: 18px;
        color: #333;
        margin-bottom: 10px;
    }

    .personal-info .grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .personal-info label {
        display: block;
        font-size: 14px;
        color: #666;
        margin-bottom: 5px;
    }

    .personal-info input,
    .personal-info select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .personal-info p {
        font-size: 14px;
        color: #333;
        margin-bottom: 10px;
        padding: 10px 0;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
    }

    .action-buttons a,
    .action-buttons button {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .action-buttons a.cancel {
        background: #ccc;
        color: #333;
    }

    .action-buttons button.save {
        background: #39ac6d;
        color: white;
    }

    .action-buttons a:hover,
    .action-buttons button:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    /* Responsive Styles */
    @media (min-width: 600px) {
        .profile-content {
            flex-direction: row;
        }

        .profile-summary {
            flex-direction: row;
            text-align: left;
        }

        .personal-info .grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 900px) {
        .container {
            padding: 30px;
        }

        .header h2 {
            font-size: 28px;
        }

        .header .actions a {
            padding: 12px 20px;
            font-size: 17px;
        }

        .profile-summary img {
            width: 100px;
            height: 100px;
        }

        .profile-summary h3 {
            font-size: 24px;
        }
    }

    @media (max-width: 599px) {
        .header {
            flex-direction: row;
            justify-content: space-between;
            gap: 8px;
        }

        .header h2 {
            font-size: 20px;
            margin: 0 5px;
        }

        .header .actions a {
            padding: 10px 15px;
            font-size: 15px;
        }

        .profile-summary {
            padding: 15px;
        }

        .quick-actions a {
            padding: 8px;
            font-size: 14px;
        }

        .action-buttons a,
        .action-buttons button {
            padding: 10px 18px;
            font-size: 15px;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="actions">
                <a href="index.php" class="home"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h2>Profile</h2>
<div class="actions">
                <?php if (!$isEditing): ?>
                    <a href="profile?edit=true" class="edit"><i class="fas fa-pen-to-square"></i></a>
                <?php endif; ?>
            </div>
        </div><hr style="color: grey; margin-bottom: 20px;">

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Personal Summary -->
            <div class="profile-summary">
                <img src="<?php echo $userProfile['avatar'] ?? 'images/profile.jpg'; ?>" alt="Profile Picture">
                <div class="info">
                    <h3><?php echo $userProfile['first_name'] . ' ' . $userProfile['last_name']; ?></h3>
                    <p><?php echo $userProfile['email']; ?></p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <a href="order_history.php">Order History</a>
                <a href="cart.php">Shopping Cart</a>
                <a href="https://wa.me/6281234567890?text=Halo%20Customer%20Service" target="_blank" style="color: green;">Customer Service (WhatsApp)</a>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="personal-info">
            <h3>Personal Information</h3>
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="grid">
                    <!-- Kolom Kiri -->
                    <div>
                        <label for="firstName">First Name</label>
                        <?php if ($isEditing): ?>
                            <input type="text" id="firstName" name="firstName" value="<?php echo $userProfile['first_name']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['first_name']; ?></p>
                        <?php endif; ?>

                        <label for="email">Email</label>
                        <?php if ($isEditing): ?>
                            <input type="email" id="email" name="email" value="<?php echo $userProfile['email']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['email']; ?></p>
                        <?php endif; ?>

                        <label for="dateOfBirth">Date of Birth</label>
                        <?php if ($isEditing): ?>
                            <input type="date" id="dateOfBirth" name="dateOfBirth" value="<?php echo $userProfile['date_of_birth']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['date_of_birth']; ?></p>
                        <?php endif; ?>

                        <label for="gender">Gender</label>
                        <?php if ($isEditing): ?>
                            <select id="gender" name="gender">
                                <option value="Male" <?php echo $userProfile['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $userProfile['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        <?php else: ?>
                            <p><?php echo $userProfile['gender']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Kolom Kanan -->
                    <div>
                        <label for="lastName">Last Name</label>
                        <?php if ($isEditing): ?>
                            <input type="text" id="lastName" name="lastName" value="<?php echo $userProfile['last_name']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['last_name']; ?></p>
                        <?php endif; ?>

                        <label for="phone">Phone</label>
                        <?php if ($isEditing): ?>
                            <input type="tel" id="phone" name="phone" value="<?php echo $userProfile['phone']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['phone']; ?></p>
                        <?php endif; ?>

                        <label for="address">Address</label>
                        <?php if ($isEditing): ?>
                            <input type="text" id="address" name="address" value="<?php echo $userProfile['address']; ?>">
                        <?php else: ?>
                            <p><?php echo $userProfile['address']; ?></p>
                        <?php endif; ?>

                        <?php if ($isEditing): ?>
                            <label for="avatar">Upload Profile Picture</label>
                            <input type="file" id="avatar" name="avatar" accept="image/*">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <?php if ($isEditing): ?>
                    <div class="action-buttons">
                        <a href="profile.php" class="cancel">Cancel</a>
                        <button type="submit" class="save">Save Changes</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>