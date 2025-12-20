<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "Akses ditolak.";
    return;
}
?>

<!-- Load Chart.js (Versi UMD Stabil) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container mx-auto p-4 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                Global Activity Log
            </h1>
            <p class="text-sm text-gray-500 mt-1">Jejak rekam aktivitas seluruh pengguna dalam sistem (Audit Trail).</p>
        </div>

        <div class="mt-4 md:mt-0 flex gap-2">
            <button onclick="loadLogs(); loadChartData('daily');" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
            <button onclick="clearAllLogs()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Bersihkan Semua
            </button>
        </div>
    </div>

    <!-- === BAGIAN GRAFIK (BARU) === -->
    <div class="bg-white p-6 rounded-lg shadow-lg border border-gray-200 mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                </svg>
                Tren Aktivitas Sistem
            </h3>

            <!-- Tombol Filter Grafik -->
            <div class="flex bg-gray-100 p-1 rounded-lg mt-3 sm:mt-0">
                <button onclick="loadChartData('daily')" id="btn-daily" class="px-3 py-1 text-sm font-medium rounded-md transition-all bg-white text-indigo-600 shadow-sm">Harian</button>
                <button onclick="loadChartData('weekly')" id="btn-weekly" class="px-3 py-1 text-sm font-medium text-gray-500 hover:text-gray-700 rounded-md transition-all">Mingguan</button>
                <button onclick="loadChartData('monthly')" id="btn-monthly" class="px-3 py-1 text-sm font-medium text-gray-500 hover:text-gray-700 rounded-md transition-all">Bulanan</button>
            </div>
        </div>

        <!-- Canvas Chart -->
        <div class="w-full h-64">
            <canvas id="activityChart"></canvas>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <input type="text" id="search-log" placeholder="Cari user atau aktivitas..." class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="w-full md:w-48">
            <select id="filter-type" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="ALL">Semua Aktivitas</option>
                <option value="LOGIN">Login / Masuk</option>
                <option value="INSERT">Tambah Data</option>
                <option value="UPDATE">Edit Data</option>
                <option value="DELETE">Hapus Data</option>
                <option value="OTHER">Lainnya</option>
            </select>
        </div>
    </div>

    <!-- TABEL LOG -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Waktu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">User / Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Aksi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi Aktivitas</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Info Teknis</th>
                    </tr>
                </thead>
                <tbody id="log-container" class="bg-white divide-y divide-gray-200 text-sm">
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const API_LOG = 'pages/development/ajax_activity_log.php';
    let searchTimeout;
    let myChart = null; // Variabel global untuk instance Chart

    // --- FUNGSI CHART ---
    function loadChartData(range) {
        // Update tampilan tombol aktif
        document.querySelectorAll('[id^="btn-"]').forEach(btn => {
            btn.classList.remove('bg-white', 'text-indigo-600', 'shadow-sm');
            btn.classList.add('text-gray-500');
        });
        const activeBtn = document.getElementById('btn-' + range);
        if (activeBtn) {
            activeBtn.classList.remove('text-gray-500');
            activeBtn.classList.add('bg-white', 'text-indigo-600', 'shadow-sm');
        }

        // Fetch data
        const formData = new FormData();
        formData.append('action', 'get_stats');
        formData.append('range', range);

        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderChart(data.labels, data.data);
                }
            })
            .catch(err => console.error("Gagal memuat chart:", err));
    }

    function renderChart(labels, dataPoints) {
        const canvas = document.getElementById('activityChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Hancurkan chart lama jika ada agar tidak menumpuk
        if (myChart) {
            myChart.destroy();
        }

        // Buat Chart Baru
        myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Aktivitas',
                    data: dataPoints,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)', // Indigo muda transparan
                    borderColor: 'rgba(79, 70, 229, 1)', // Indigo solid
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: 'rgba(79, 70, 229, 1)',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3 // Membuat garis melengkung halus (spline)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // Sembunyikan legend karena cuma 1 dataset
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 4],
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 1 // Agar sumbu Y bilangan bulat (tidak ada 0.5 aktivitas)
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // --- FUNGSI TABEL LOG ---
    function loadLogs() {
        const search = document.getElementById('search-log').value;
        const type = document.getElementById('filter-type').value;
        const container = document.getElementById('log-container');

        const formData = new FormData();
        formData.append('action', 'get_logs');
        formData.append('search', search);
        formData.append('type', type);

        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.error(data.message);
                    return;
                }

                if (data.data.length === 0) {
                    container.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 italic">Tidak ada aktivitas ditemukan.</td></tr>';
                    return;
                }

                let html = '';
                data.data.forEach(log => {
                    let badgeClass = 'bg-gray-100 text-gray-800';
                    if (log.action_type == 'LOGIN') badgeClass = 'bg-blue-100 text-blue-800';
                    else if (log.action_type == 'INSERT') badgeClass = 'bg-green-100 text-green-800';
                    else if (log.action_type == 'UPDATE') badgeClass = 'bg-yellow-100 text-yellow-800';
                    else if (log.action_type == 'DELETE') badgeClass = 'bg-red-100 text-red-800';

                    html += `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 align-top">
                        <div class="font-bold text-gray-700">${log.time_ago}</div>
                        <div class="text-xs text-gray-400">${log.date_fmt}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-top">
                        <div class="font-medium text-gray-900">${log.user_name}</div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">${log.role || 'Guest'}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap align-top">
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass}">
                            ${log.action_type}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-700 break-words align-top">
                        ${log.description}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-gray-400 font-mono align-top">
                        <div class="mb-1">IP: <span class="text-gray-600">${log.ip_address}</span></div>
                        <div class="truncate max-w-[200px] inline-block cursor-help border-b border-dotted border-gray-300" title="${log.user_agent}">
                            ${log.user_agent ? log.user_agent : '-'}
                        </div>
                    </td>
                </tr>
            `;
                });
                container.innerHTML = html;
            });
    }

    function clearAllLogs() {
        if (!confirm('Apakah Anda yakin ingin menghapus SELURUH riwayat aktivitas? Data tidak bisa dikembalikan.')) return;

        const formData = new FormData();
        formData.append('action', 'clear_logs');
        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Log berhasil dibersihkan.');
                    loadLogs();
                    loadChartData('daily'); // Refresh chart juga
                }
            });
    }

    // Event Listeners untuk Filter Real-time
    document.getElementById('search-log').addEventListener('keyup', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadLogs, 500);
    });
    document.getElementById('filter-type').addEventListener('change', loadLogs);

    // Load awal
    document.addEventListener('DOMContentLoaded', () => {
        loadLogs();
        loadChartData('daily');
    });
</script>