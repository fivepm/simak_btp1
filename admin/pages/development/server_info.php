<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "Akses ditolak.";
    return;
}
?>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                System Health & Status
            </h1>
            <p class="text-sm text-gray-500 mt-1">Monitoring kondisi server dan konektivitas.</p>
        </div>
        <button onclick="loadAllStats()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh Data
        </button>
    </div>

    <!-- GRID UTAMA -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">

        <!-- CARD 1: PHP & SERVER -->
        <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-blue-500 relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <h3 class="font-bold text-gray-700">Server Environment</h3>
                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">PHP Info</span>
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">PHP Version</span>
                    <span class="font-mono font-medium" id="php-ver">Loading...</span>
                </div>
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Web Server</span>
                    <span class="font-mono font-medium truncate w-1/2 text-right" id="srv-soft">...</span>
                </div>
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Memory Limit</span>
                    <span class="font-mono font-medium" id="mem-limit">...</span>
                </div>
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">Max Upload</span>
                    <span class="font-mono font-medium" id="max-up">...</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Max Execution</span>
                    <span class="font-mono font-medium" id="max-exec">...</span>
                </div>
            </div>
        </div>

        <!-- CARD 2: DATABASE -->
        <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-yellow-500">
            <div class="flex justify-between items-start mb-4">
                <h3 class="font-bold text-gray-700">Database Info</h3>
                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">MySQL</span>
            </div>
            <div class="flex items-center justify-center py-4">
                <div class="text-center">
                    <div class="text-4xl font-bold text-gray-800 mb-1" id="db-size">...</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Ukuran Database</div>
                </div>
            </div>
            <div class="space-y-2 text-sm mt-2">
                <div class="flex justify-between border-b pb-1">
                    <span class="text-gray-500">DB Name</span>
                    <span class="font-mono font-medium" id="db-name">...</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">MySQL Ver</span>
                    <span class="font-mono font-medium" id="mysql-ver">...</span>
                </div>
            </div>
        </div>

        <!-- CARD 3: DISK USAGE -->
        <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-purple-500">
            <div class="flex justify-between items-start mb-4">
                <h3 class="font-bold text-gray-700">Disk Usage</h3>
                <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Storage</span>
            </div>

            <div class="mb-2 flex justify-between text-sm">
                <span class="text-gray-600">Used: <strong id="disk-used">...</strong></span>
                <span class="text-gray-600">Total: <strong id="disk-total">...</strong></span>
            </div>

            <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
                <div id="disk-bar" class="bg-purple-600 h-4 rounded-full transition-all duration-1000 ease-out" style="width: 0%"></div>
            </div>

            <div class="text-center">
                <span class="text-3xl font-bold text-purple-700" id="disk-percent">0%</span>
                <span class="text-gray-500 text-sm">Terpakai</span>
            </div>
        </div>
    </div>

    <!-- ROW 2: KONEKTIVITAS & EKSTENSI -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- API PING CHECK -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Connectivity Status
            </h3>
            <div class="space-y-4">
                <!-- Google Ping -->
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded shadow-sm">
                            <img src="https://www.google.com/favicon.ico" class="w-5 h-5" alt="G">
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Internet (Google)</p>
                            <p class="text-xs text-gray-500">Global Connectivity</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold" id="ping-google-status">Checking...</p>
                        <p class="text-xs text-gray-500" id="ping-google-latency">-</p>
                    </div>
                </div>

                <!-- Fonnte Ping -->
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-white rounded shadow-sm">
                            <span class="text-green-600 font-bold text-sm">F</span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">Fonnte API</p>
                            <p class="text-xs text-gray-500">WhatsApp Gateway</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold" id="ping-fonnte-status">Checking...</p>
                        <p class="text-xs text-gray-500" id="ping-fonnte-latency">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP EXTENSIONS -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
                Required PHP Extensions
            </h3>
            <div class="grid grid-cols-2 gap-4" id="ext-container">
                <!-- Diisi via JS -->
                <div class="text-gray-400 text-sm italic">Loading...</div>
            </div>
        </div>

    </div>
</div>

<script>
    const API_URL = 'pages/development/ajax_server_info.php';

    function loadAllStats() {
        // 1. Get General Stats
        const formData = new FormData();
        formData.append('action', 'get_stats');

        fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const d = res.data;

                    // PHP Info
                    document.getElementById('php-ver').innerText = d.php_version;
                    document.getElementById('srv-soft').innerText = d.server_software;
                    document.getElementById('mem-limit').innerText = d.memory_limit;
                    document.getElementById('max-up').innerText = d.max_upload;
                    document.getElementById('max-exec').innerText = d.max_execution;

                    // DB Info
                    document.getElementById('db-name').innerText = d.db_name;
                    document.getElementById('db-size').innerText = d.db_size;
                    document.getElementById('mysql-ver').innerText = d.mysql_version;

                    // Disk Info
                    document.getElementById('disk-used').innerText = d.disk_used;
                    document.getElementById('disk-total').innerText = d.disk_total;
                    document.getElementById('disk-percent').innerText = d.disk_percent + '%';
                    document.getElementById('disk-bar').style.width = d.disk_percent + '%';

                    // Warna Bar Disk
                    const bar = document.getElementById('disk-bar');
                    if (d.disk_percent > 80) bar.className = 'h-4 rounded-full bg-red-600 transition-all duration-1000';
                    else if (d.disk_percent > 50) bar.className = 'h-4 rounded-full bg-yellow-500 transition-all duration-1000';
                    else bar.className = 'h-4 rounded-full bg-purple-600 transition-all duration-1000';

                    // Extensions
                    const extCont = document.getElementById('ext-container');
                    extCont.innerHTML = '';
                    d.extensions.forEach(ext => {
                        const icon = ext.active ?
                            '<svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>' :
                            '<svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';

                        const cls = ext.active ? 'text-gray-700' : 'text-red-500 line-through';

                        extCont.innerHTML += `
                    <div class="flex items-center justify-between bg-gray-50 p-2 rounded">
                        <span class="text-sm font-medium ${cls} uppercase">${ext.name}</span>
                        ${icon}
                    </div>
                `;
                    });
                }
            });

        // 2. Check Ping (Asynchronous)
        checkPing('google');
        checkPing('fonnte');
    }

    function checkPing(target) {
        const statusEl = document.getElementById(`ping-${target}-status`);
        const latEl = document.getElementById(`ping-${target}-latency`);

        statusEl.innerText = "Pinging...";
        latEl.innerText = "";

        const formData = new FormData();
        formData.append('action', 'check_ping');
        formData.append('target', target);

        fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                statusEl.innerText = res.status;
                statusEl.className = `font-bold ${res.class}`;
                latEl.innerText = res.latency;
            });
    }

    document.addEventListener('DOMContentLoaded', loadAllStats);
</script>