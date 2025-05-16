<?php
// Mulai session
session_start();

// Hapus semua data session
session_unset();

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: login-pengguna");
exit; // Pastikan tidak ada kode yang dieksekusi setelah redirect
?>