<?php
header('Content-Type: application/json'); // Mengatur header respons sebagai JSON
header('Access-Control-Allow-Origin: *'); // Mengizinkan akses dari domain mana pun (untuk pengembangan)

// Nonaktifkan pelaporan kesalahan untuk produksi, aktifkan untuk debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Permintaan tidak valid.',
    'links' => []
];

// Periksa apakah permintaan adalah POST dan memiliki URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);

    // Validasi URL sederhana
    if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
        // Path ke executable yt-dlp. Anda mungkin perlu menyesuaikan ini.
        // Contoh: '/usr/local/bin/yt-dlp' atau 'C:\Python\Scripts\yt-dlp.exe'
        $yt_dlp_path = 'yt-dlp'; // Coba jalur default dulu, atau berikan jalur absolut

        // Perintah untuk mendapatkan informasi JSON dari video (termasuk format)
        // Opsi --dump-single-json akan memberikan output JSON dari metadata video
        // Opsi -f "bestaudio[ext=m4a]/bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best"
        // mencoba mendapatkan format terbaik: audio m4a, atau video mp4+audio m4a, atau video mp4, atau yang terbaik
        // Opsi --flat-playlist dan --no-playlist digunakan untuk memastikan hanya satu item yang diambil
        // --no-warnings untuk menekan peringatan yang tidak perlu di output
        // --no-simulate untuk benar-benar mengambil URL, bukan hanya mensimulasikan
        // --print-json untuk mengeluarkan JSON
        $command = escapeshellarg($yt_dlp_path) . ' --ignore-errors --dump-json ' . escapeshellarg($url);

        $output = [];
        $return_var = 0;

        // Jalankan perintah yt-dlp
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $json_output = implode("\n", $output);
            $video_info = json_decode($json_output, true);

            if (json_last_error() === JSON_ERROR_NONE && $video_info) {
                $download_links = [];

                // Filter format yang relevan (misalnya mp4, webm, mp3, m4a)
                if (isset($video_info['formats']) && is_array($video_info['formats'])) {
                    foreach ($video_info['formats'] as $format) {
                        // Periksa apakah format memiliki url dan ext yang valid
                        if (isset($format['url']) && isset($format['ext'])) {
                            $ext = $format['ext'];
                            $filesize_mb = isset($format['filesize']) ? round($format['filesize'] / (1024 * 1024), 2) : 'N/A';
                            $format_note = isset($format['format_note']) ? " ({$format['format_note']})" : '';
                            $resolution = isset($format['height']) ? "{$format['height']}p" : (isset($format['vcodec']) && $format['vcodec'] !== 'none' ? 'Video' : 'Audio');

                            $label = '';
                            $url_to_download = $format['url'];

                            // Filter format yang umum dan bisa diunduh
                            if ($ext === 'mp4' && $resolution !== 'Audio' && isset($format['height'])) {
                                $label = "MP4 {$resolution}{$format_note} ({$filesize_mb} MB)";
                            } elseif ($ext === 'webm' && $resolution !== 'Audio' && isset($format['height'])) {
                                $label = "WEBM {$resolution}{$format_note} ({$filesize_mb} MB)";
                            } elseif (($ext === 'mp3' || $ext === 'm4a' || $ext === 'aac') && $resolution === 'Audio') {
                                $abr = isset($format['abr']) ? "{$format['abr']}kbps" : '';
                                $label = strtoupper($ext) . " Audio {$abr}{$format_note} ({$filesize_mb} MB)";
                            } elseif ($ext === 'mp4' && $resolution === 'Audio') {
                                // mp4 audio only
                                $abr = isset($format['abr']) ? "{$format['abr']}kbps" : '';
                                $label = "MP4 Audio {$abr}{$format_note} ({$filesize_mb} MB)";
                            }


                            if (!empty($label)) {
                                $download_links[] = [
                                    'label' => $label,
                                    'url' => $url_to_download
                                ];
                            }
                        }
                    }
                }

                // Jika tidak ada link unduh yang valid ditemukan
                if (empty($download_links)) {
                    $response['message'] = 'Tidak ada format unduhan yang kompatibel ditemukan.';
                } else {
                    $response['status'] = 'success';
                    $response['message'] = 'Link unduh berhasil diambil.';
                    $response['links'] = $download_links;
                }

            } else {
                $response['message'] = 'Gagal mem-parsing informasi video dari yt-dlp. Mungkin URL tidak valid atau yt-dlp bermasalah.';
                // Untuk debugging, uncomment baris di bawah:
                // $response['debug_output'] = $json_output;
                // $response['json_error'] = json_last_error_msg();
            }
        } else {
            $response['message'] = 'Gagal menjalankan yt-dlp. Pastikan yt-dlp terinstal dan berada di PATH server, atau atur path_to_yt-dlp dengan benar. Error: ' . implode("\n", $output);
        }
    } else {
        $response['message'] = 'URL yang diberikan tidak valid.';
    }
}

echo json_encode($response);
?>
