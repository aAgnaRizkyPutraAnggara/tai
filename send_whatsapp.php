<?php
// send_whatsapp.php - Disesuaikan untuk API Fonnte (Revisi dengan URL Foto di Pesan)

// Izinkan CORS jika perlu (hanya untuk pengembangan, dalam produksi gunakan domain spesifik)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Tangkap data dari request POST
$itemName = $_POST['itemName'] ?? 'Tidak diketahui';
$country = $_POST['country'] ?? 'Tidak diketahui';
$platform = $_POST['platform'] ?? 'Tidak diketahui';
$quantity = $_POST['quantity'] ?? 'Tidak diketahui';
$totalPrice = $_POST['totalPrice'] ?? 'Rp 0';
$paymentMethod = $_POST['paymentMethod'] ?? 'Tidak diketahui';

// Nomor WhatsApp penerima (Hardcoded sesuai permintaan sebelumnya)
$recipientNumber = '081511652007'; 

// Token API dari Fonnte (Pastikan ini HANYA di sisi server)
$fonnte_api_token = 'aj8u3QTk1xGzNhw7aPgd';
// URL API Fonnte untuk mengirim pesan
$fonnte_api_url = 'https://api.fonnte.com/send'; // Ini adalah endpoint yang benar sesuai docs

$image_url = null;
$unique_file_name = null; // Inisialisasi null untuk digunakan nanti
$upload_dir = 'uploads/'; // Direktori tempat menyimpan gambar bukti pembayaran

// Pastikan direktori uploads ada dan writable
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Buat direktori dengan izin penuh jika belum ada
}

// Penanganan pengunggahan file
if (isset($_FILES['proofPhoto']) && $_FILES['proofPhoto']['error'] == UPLOAD_ERR_OK) {
    $file_tmp_name = $_FILES['proofPhoto']['tmp_name'];
    // Amankan nama file untuk mencegah directory traversal dan buat unik
    $original_file_name = basename($_FILES['proofPhoto']['name']);
    $extension = pathinfo($original_file_name, PATHINFO_EXTENSION);
    $unique_file_name = uniqid('proof_') . '.' . $extension; // Nama file unik
    $file_path = $upload_dir . $unique_file_name;

    if (move_uploaded_file($file_tmp_name, $file_path)) {
        // Buat URL publik untuk gambar yang diunggah
        // PENTING: Pastikan ini adalah URL yang dapat diakses Fonnte dan dari internet
        $image_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $file_path;
    } else {
        // Jika upload gagal, berikan pesan error
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah file bukti pembayaran ke server.']);
        exit();
    }
}

// === BAGIAN UTAMA PEMBUATAN PESAN TEKS / CAPTION ===
$message_text_parts = [
    "Konfirmasi Pembayaran Anda:",
    "------------------------------------",
    "Wa Pembeli: " . $itemName,
    "Negara: " . $country,
    "Platform: " . $platform,
    "Jumlah: " . $quantity,
    "Total Harga: " . $totalPrice,
    "Metode Pembayaran: " . $paymentMethod,
    "------------------------------------"
];

if ($image_url) {
    // Tambahkan URL foto ke pesan teks jika ada gambar
    $message_text_parts[] = "Link Bukti Foto: " . $image_url;
    $message_text_parts[] = "\n*Bukti pembayaran terlampir di atas. Terima kasih!*"; // Pesan ini akan jadi caption foto
} else {
    $message_text_parts[] = "Mohon segera unggah bukti pembayaran jika belum. Terima kasih!";
}

$message_text = implode("\n", $message_text_parts); // Gabungkan semua detail menjadi satu string dengan baris baru


// Data untuk dikirim ke API Fonnte
$payload = [
    'target' => $recipientNumber,
    'message' => $message_text, // Ini akan menjadi caption gambar jika ada 'url'
];

// Jika ada gambar, tambahkan parameter 'url' dan 'filename' untuk Fonnte
if ($image_url) {
    $payload['url'] = $image_url;
    $payload['filename'] = $unique_file_name; // Nama file untuk ditampilkan di WA
}


// Inisialisasi cURL
$ch = curl_init($fonnte_api_url);

// Set opsi cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Mengembalikan transfer sebagai string
curl_setopt($ch, CURLOPT_POST, true);           // Mengatur request sebagai POST
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); // Mengirim payload sebagai array (multipart/form-data)

// Menambahkan header Authorization dengan token Fonnte
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $fonnte_api_token // Format Authorization Fonnte adalah "Authorization: YOUR_TOKEN"
]);

// Eksekusi request cURL
$response = curl_exec($ch);

// Tangani error cURL
if (curl_errno($ch)) {
    echo json_encode(['status' => 'error', 'message' => 'cURL Error: ' . curl_error($ch)]);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $api_response = json_decode($response, true); // Dekode respons JSON dari Fonnte

    if ($http_code == 200 && isset($api_response['status']) && $api_response['status'] === true) {
        echo json_encode(['status' => 'success', 'message' => 'Pesan WhatsApp berhasil dikirim.', 'api_response' => $api_response]);
    } else {
        $error_message = 'Gagal mengirim pesan WhatsApp.';
        if (isset($api_response['reason'])) {
            $error_message .= ' Alasan: ' . $api_response['reason'];
        } elseif (isset($api_response['message'])) {
            $error_message .= ' Pesan: ' . $api_response['message'];
        }
        echo json_encode(['status' => 'error', 'message' => $error_message . ' Kode HTTP: ' . $http_code . '. Respon API Mentah: ' . $response, 'api_response' => $api_response]);
    }
}

// Tutup cURL
curl_close($ch);
?>
