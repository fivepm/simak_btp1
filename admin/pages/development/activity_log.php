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

    <!-- === BAGIAN GRAFIK (ADVANCED) === -->
    <div class="bg-white p-6 rounded-lg shadow-lg border border-gray-200 mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
            <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                </svg>
                Statistik Aktivitas
            </h3>

            <!-- Kontrol Grafik -->
            <div class="flex flex-col sm:flex-row gap-2 bg-gray-50 p-2 rounded-lg border border-gray-200">
                <!-- 1. Pilih Mode -->
                <select id="chart-mode" class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="updateChartInput()">
                    <option value="daily">Harian (Per Jam)</option>
                    <option value="weekly">Mingguan (Per Hari)</option>
                    <option value="monthly">Bulanan (Per Tanggal)</option>
                </select>

                <!-- 2. Input Tanggal (Dinamis berubah sesuai mode) -->
                <div id="chart-input-container">
                    <!-- Default: Input Date -->
                    <input type="date" id="chart-filter-daily" class="border-gray-300 rounded text-sm" value="<?php echo date('Y-m-d'); ?>">
                    <!-- Input Week (Hidden Awal) -->
                    <input type="week" id="chart-filter-weekly" class="hidden border-gray-300 rounded text-sm" value="<?php echo date('Y-\WW'); ?>">
                    <!-- Input Month (Hidden Awal) -->
                    <input type="month" id="chart-filter-monthly" class="hidden border-gray-300 rounded text-sm" value="<?php echo date('Y-m'); ?>">
                </div>

                <!-- 3. Tombol Refresh -->
                <button onclick="loadChartData()" class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 text-sm font-medium transition">
                    Tampilkan
                </button>
            </div>
        </div>

        <!-- Canvas Chart -->
        <div class="w-full h-72">
            <canvas id="activityChart"></canvas>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6 flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <input type="text" id="search-log" placeholder="Pilih tanggal (YYYY-MM-DD), Cari user atau aktivitas..." class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="w-full md:w-48">
            <select id="filter-type" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="ALL">Semua Aktivitas</option>
                <option value="LOGIN">Login / Masuk</option>
                <option value="LOGOUT">Logout / Keluar</option>
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

    // --- LOGIKA UI PICKER ---
    function updateChartInput() {
        const mode = document.getElementById('chart-mode').value;

        // Sembunyikan semua input
        document.getElementById('chart-filter-daily').classList.add('hidden');
        document.getElementById('chart-filter-weekly').classList.add('hidden');
        document.getElementById('chart-filter-monthly').classList.add('hidden');

        // Tampilkan input yang sesuai
        document.getElementById('chart-filter-' + mode).classList.remove('hidden');
    }

    // --- FUNGSI CHART ---
    function loadChartData() {
        const mode = document.getElementById('chart-mode').value;
        let filterValue = '';

        // Ambil value dari input yang aktif
        if (mode === 'daily') filterValue = document.getElementById('chart-filter-daily').value;
        else if (mode === 'weekly') filterValue = document.getElementById('chart-filter-weekly').value;
        else if (mode === 'monthly') filterValue = document.getElementById('chart-filter-monthly').value;

        if (!filterValue) {
            alert("Silakan pilih tanggal/periode terlebih dahulu.");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_stats');
        formData.append('mode', mode);
        formData.append('filter_value', filterValue);

        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderChart(data.labels, data.data, mode);
                } else {
                    console.error("Chart Error:", data.message);
                }
            })
            .catch(err => console.error("Gagal memuat chart:", err));
    }

    function renderChart(labels, dataPoints, mode) {
        const canvas = document.getElementById('activityChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (myChart) myChart.destroy();

        // Tentukan Label X-Axis
        let xLabel = 'Jam';
        if (mode === 'weekly') xLabel = 'Hari';
        if (mode === 'monthly') xLabel = 'Tanggal';

        // Plugin Inline untuk menampilkan nilai di atas titik
        const floatingLabelsPlugin = {
            id: 'floatingLabels',
            afterDatasetsDraw(chart, args, options) {
                const {
                    ctx
                } = chart;
                ctx.save();
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((element, index) => {
                        const dataValue = dataset.data[index];
                        if (dataValue > 0) { // Hanya tampilkan jika nilai > 0 agar tidak penuh
                            ctx.font = 'bold 12px sans-serif';
                            ctx.fillStyle = '#4F46E5'; // Warna Indigo
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            // Gambar teks sedikit di atas titik
                            ctx.fillText(dataValue, element.x, element.y - 8);
                        }
                    });
                });
                ctx.restore();
            }
        };

        myChart = new Chart(ctx, {
            type: 'line', // Kembali ke Line Chart
            plugins: [floatingLabelsPlugin], // Daftarkan plugin inline
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Aktivitas',
                    data: dataPoints,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)', // Fill area transparan
                    borderColor: 'rgba(79, 70, 229, 1)', // Garis solid
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff', // Titik putih
                    pointBorderColor: 'rgba(79, 70, 229, 1)', // Border titik indigo
                    pointRadius: 5, // Ukuran titik lebih besar
                    pointHoverRadius: 7, // Hover lebih besar
                    fill: true, // Isi area bawah garis
                    tension: 0.3 // Garis melengkung halus
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 20 // Tambah padding atas agar angka tidak terpotong
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            title: function(context) {
                                return xLabel + ': ' + context[0].label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }, // Bilangan bulat
                        grid: {
                            borderDash: [2, 4],
                            color: 'rgba(0,0,0,0.05)'
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
                    else if (log.action_type == 'LOGOUT') badgeClass = 'bg-purple-100 text-purple-800';
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
        loadChartData(); // Default Harian (Hari ini)
    });
</script>