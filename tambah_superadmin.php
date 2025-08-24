<?php

// 1. PENGATURAN KONEKSI DATABASE
$hostname = "localhost";    // Biasanya "localhost"
$db_username = "root";      // Ganti dengan username database Anda
$db_password = "";          // Ganti dengan password database Anda
$database_name = "siabsibtp1";

// Membuat koneksi ke database
$conn = new mysqli($hostname, $db_username, $db_password, $database_name);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

echo "Koneksi database berhasil.<br>";

// 2. DATA SUPERADMIN
$nama = "Super Admin";
// Karena 'kelompok' & 'tingkat' diwajibkan (NOT NULL), kita isi salah satu sebagai default untuk admin.
// Anda bisa sesuaikan ini jika logikanya berbeda.
$kelompok = "sunten";
$role = "admin";
$tingkat = "desa";
$username = "superadmin354";
$password_plain = "adminutama313"; // Ganti dengan password yang aman

// 3. MEMBUAT BARCODE OTOMATIS
// Menghasilkan ID unik berdasarkan waktu saat ini dengan prefix 'SA-' (Super Admin)
$barcode = 'SA-68a4fcc0aea54';

// 4. HASHING PASSWORD (SANGAT PENTING UNTUK KEAMANAN)
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

// 5. MENYIAPKAN PERINTAH SQL (MENGGUNAKAN PREPARED STATEMENTS)
// Ini adalah cara paling aman untuk menjalankan query agar terhindar dari SQL Injection
$sql = "INSERT INTO users (nama, kelompok, role, tingkat, barcode, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Gagal menyiapkan statement: " . $conn->error);
}

// Mengikat parameter ke statement SQL
// 'sssssss' berarti semua 7 variabel adalah tipe data string
$stmt->bind_param("sssssss", $nama, $kelompok, $role, $tingkat, $barcode, $username, $password_hashed);

// 6. MENJALANKAN QUERY DAN MEMBERI RESPON
if ($stmt->execute()) {
    echo "=========================================<br>";
    echo "✅ **User Superadmin berhasil ditambahkan!**<br>";
    echo "=========================================<br>";
    echo "Nama: " . htmlspecialchars($nama) . "<br>";
    echo "Username: " . htmlspecialchars($username) . "<br>";
    echo "Password: " . htmlspecialchars($password_plain) . " (Segera ganti setelah login!)<br>";
    echo "Barcode: " . htmlspecialchars($barcode) . "<br>";
} else {
    // Cek jika username sudah ada
    if ($conn->errno == 1062) { // 1062 adalah kode error untuk duplicate entry
        echo "❌ **Gagal:** Username '" . htmlspecialchars($username) . "' sudah terdaftar.";
    } else {
        echo "❌ **Gagal menambahkan user:** " . $stmt->error;
    }
}

// 7. MENUTUP KONEKSI
$stmt->close();
$conn->close();
