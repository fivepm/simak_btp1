<?php
// Atur header HTTP 503 Service Unavailable. 
// Ini memberi tahu mesin pencari bahwa situs sedang nonaktif sementara dan akan kembali lagi.
// Ini penting untuk SEO!
header('HTTP/1.1 503 Service Unavailable');
header('Retry-After: 3600'); // Coba lagi setelah 1 jam (3600 detik)
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Sedang dalam Perbaikan</title>
    <link rel="icon" type="image/png" href="assets/images/logo_web_bg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
        }

        /* CSS untuk animasi baru */
        .gear {
            animation: spin 10s linear infinite;
            transform-origin: 100px 130px;
            /* Pusat rotasi gear */
        }

        .wrench {
            animation: wrench-move 2.5s ease-in-out infinite alternate;
            transform-origin: 80px 105px;
            /* Pivot untuk kunci pas */
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes wrench-move {
            from {
                transform: rotate(-10deg);
            }

            to {
                transform: rotate(15deg);
            }
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="container mx-auto p-4 text-center">
        <div class="bg-white p-8 sm:p-12 rounded-2xl shadow-lg max-w-2xl mx-auto">
            <div class="mx-auto w-24 h-24 text-cyan-500 mb-6">
                <!-- <i class="fa-solid fa-screwdriver-wrench"></i> -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free v6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                    <path d="M78.6 5C69.1-2.4 55.6-1.5 47 7L7 47c-8.5 8.5-9.4 22-2.1 31.6l80 104c4.5 5.9 11.6 9.4 19 9.4l54.1 0 109 109c-14.7 29-10 65.4 14.3 89.6l112 112c12.5 12.5 32.8 12.5 45.3 0l64-64c12.5-12.5 12.5-32.8 0-45.3l-112-112c-24.2-24.2-60.6-29-89.6-14.3l-109-109 0-54.1c0-7.5-3.5-14.5-9.4-19L78.6 5zM19.9 396.1C7.2 408.8 0 426.1 0 444.1C0 481.6 30.4 512 67.9 512c18 0 35.3-7.2 48-19.9L233.7 374.3c-7.8-20.9-9-43.6-3.6-65.1l-61.7-61.7L19.9 396.1zM512 144c0-10.5-1.1-20.7-3.2-30.5c-2.4-11.2-16.1-14.1-24.2-6l-63.9 63.9c-3 3-7.1 4.7-11.3 4.7L352 176c-8.8 0-16-7.2-16-16l0-57.4c0-4.2 1.7-8.3 4.7-11.3l63.9-63.9c8.1-8.1 5.2-21.8-6-24.2C388.7 1.1 378.5 0 368 0C288.5 0 224 64.5 224 144l0 .8 85.3 85.3c36-9.1 75.8 .5 104 28.7L429 274.5c49-23 83-72.8 83-130.5zM56 432a24 24 0 1 1 48 0 24 24 0 1 1 -48 0z" />
                </svg>
            </div>
            <hr class="text-black-500">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 mt-3 mb-3">Mohon Maaf<br>SIMAK Banguntapan 1<br>Sedang dalam Proses Maintenance (Perbaikan)</h1>
            <p class="text-gray-600 text-lg">
                Kami sedang melakukan beberapa pembaruan untuk meningkatkan performa dan fitur pada sistem ini. Mohon untuk kembali lagi dalam beberapa saat.
            </p>
            <p class="text-gray-600 text-2xl mt-2">
                الحمدلله جزاكم الله خيرا
            </p>
        </div>
    </div>
</body>

</html>