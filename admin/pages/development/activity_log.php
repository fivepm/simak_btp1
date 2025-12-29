<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo "Akses ditolak.";
    return;
}
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Load Date-fns (VERSI FIXED: cdn.min.js) -->
<script src="https://cdn.jsdelivr.net/npm/date-fns@2.30.0/cdn.min.js"></script>

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
            <button onclick="loadLogs(1); loadChartData();" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
            <!-- <button onclick="clearAllLogs()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 flex items-center gap-2 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Bersihkan Semua
            </button> -->
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

            <div class="flex flex-col sm:flex-row gap-2 bg-gray-50 p-2 rounded-lg border border-gray-200">
                <select id="chart-mode" class="border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="updateChartInput()">
                    <option value="daily">Harian (Per Jam)</option>
                    <option value="weekly">Mingguan (Per Hari)</option>
                    <option value="monthly">Bulanan (Per Tanggal)</option>
                </select>

                <div id="chart-input-container">
                    <input type="date" id="chart-filter-daily" class="border-gray-300 rounded text-sm" value="<?php echo date('Y-m-d'); ?>">
                    <input type="week" id="chart-filter-weekly" class="hidden border-gray-300 rounded text-sm" value="<?php echo date('Y-\WW'); ?>">
                    <input type="month" id="chart-filter-monthly" class="hidden border-gray-300 rounded text-sm" value="<?php echo date('Y-m'); ?>">
                </div>

                <button onclick="loadChartData()" class="bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 text-sm font-medium transition">
                    Tampilkan
                </button>
            </div>
        </div>

        <div class="w-full h-72 relative">
            <canvas id="activityChart"></canvas>
        </div>
    </div>

    <!-- FILTER & TABEL LOG -->
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
                <option value="EXPORT">Ekspor Data</option>
                <option value="MAINTENANCE">Maintenance</option>
                <option value="OTHER">Lainnya</option>
            </select>
        </div>
    </div>

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

        <!-- PAGINATION -->
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
            <div class="text-sm text-gray-500">
                Menampilkan <span id="page-info-start" class="font-medium">0</span> sampai <span id="page-info-end" class="font-medium">0</span> dari <span id="page-info-total" class="font-medium">0</span> data
            </div>
            <div class="flex gap-2">
                <button id="btn-prev" onclick="changePage(-1)" disabled class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Sebelumnya</button>
                <button id="btn-next" onclick="changePage(1)" disabled class="px-3 py-1 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Selanjutnya</button>
            </div>
        </div>
    </div>
</div>

<script>
    const API_LOG = 'pages/development/ajax_activity_log.php';
    let searchTimeout;
    let myChart = null;
    let currentPage = 1;

    // --- HELPERS ---
    function formatLogText(text) {
        if (!text) return '';
        let safeText = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        safeText = safeText.replace(/\*(.*?)\*/g, '<strong class="font-bold text-gray-900">$1</strong>');
        safeText = safeText.replace(/_(.*?)_/g, '<em class="italic text-gray-600">$1</em>');
        safeText = safeText.replace(/`(.*?)`/g, '<code class="bg-gray-100 text-red-600 px-1 rounded font-mono text-xs border border-gray-200">$1</code>');
        return safeText;
    }

    function updateChartInput() {
        const mode = document.getElementById('chart-mode').value;
        document.getElementById('chart-filter-daily').classList.add('hidden');
        document.getElementById('chart-filter-weekly').classList.add('hidden');
        document.getElementById('chart-filter-monthly').classList.add('hidden');
        document.getElementById('chart-filter-' + mode).classList.remove('hidden');
    }

    // --- LOGIKA UTAMA GRAFIK ---
    function loadChartData() {
        const mode = document.getElementById('chart-mode').value;
        const search = document.getElementById('search-log').value;
        const type = document.getElementById('filter-type').value;

        let filterValue = '';
        if (mode === 'daily') filterValue = document.getElementById('chart-filter-daily').value;
        else if (mode === 'weekly') filterValue = document.getElementById('chart-filter-weekly').value;
        else if (mode === 'monthly') filterValue = document.getElementById('chart-filter-monthly').value;

        if (!filterValue) {
            alert("Pilih tanggal/periode.");
            return;
        }

        const formData = new FormData();
        formData.append('action', 'get_stats');
        formData.append('mode', mode);
        formData.append('filter_value', filterValue);
        formData.append('search', search);
        formData.append('type', type);

        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderChart(data.labels, data.data, mode, type, data.maintenance, filterValue);
                } else {
                    console.error("Chart Error:", data.message);
                }
            })
            .catch(err => console.error("Gagal memuat chart:", err));
    }

    function renderChart(labels, dataPoints, mode, filterType, maintenanceData, filterValue) {
        const canvas = document.getElementById('activityChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (myChart) myChart.destroy();

        let xLabel = 'Jam';
        if (mode === 'weekly') xLabel = 'Hari';
        if (mode === 'monthly') xLabel = 'Tanggal';

        // Warna Garis
        let colorBase = '17, 24, 39'; // darkbase
        if (filterType === 'LOGIN') colorBase = '59, 130, 246'; // biru
        else if (filterType === 'LOGOUT') colorBase = '79, 70, 229'; // indigo
        else if (filterType === 'INSERT') colorBase = '16, 185, 129'; // hijau
        else if (filterType === 'UPDATE') colorBase = '245, 158, 11'; // kuning
        else if (filterType === 'DELETE') colorBase = '239, 68, 68'; // merah
        else if (filterType === 'EXPORT') colorBase = '249, 115, 22'; // orange
        else if (filterType === 'MAINTENANCE') colorBase = '6, 182, 212'; // cyan
        else if (filterType === 'OTHER') colorBase = '156, 163, 175'; // abu

        const borderColor = `rgba(${colorBase}, 1)`;
        const bgColor = `rgba(${colorBase}, 0.1)`;

        // --- PLUGIN: BACKGROUND MAINTENANCE ---
        const maintenancePlugin = {
            id: 'maintenanceHighlight',
            beforeDraw(chart, args, options) {
                const {
                    ctx,
                    chartArea: {
                        top,
                        bottom,
                        left,
                        right,
                        width,
                        height
                    },
                    scales: {
                        x,
                        y
                    }
                } = chart;

                if (!maintenanceData || maintenanceData.length === 0) return;

                ctx.save();

                maintenanceData.forEach(maint => {
                    const start = new Date(maint.start);
                    const end = new Date(maint.end);

                    let startX = null;
                    let endX = null;

                    // --- LOGIKA MAPPING WAKTU KE PIXEL CANVAS ---

                    if (mode === 'daily') {
                        // FilterValue = YYYY-MM-DD
                        // Cek apakah maintenance terjadi di hari ini
                        const viewDate = new Date(filterValue);
                        if (start.toDateString() === viewDate.toDateString() || end.toDateString() === viewDate.toDateString() || (start < viewDate && end > viewDate)) {

                            // Hitung start hour & end hour
                            let startHour = 0;
                            let endHour = 23;

                            if (start.toDateString() === viewDate.toDateString()) startHour = start.getHours() + (start.getMinutes() / 60);
                            if (end.toDateString() === viewDate.toDateString()) endHour = end.getHours() + (end.getMinutes() / 60);

                            // Dapatkan pixel (asumsi 24 label index 0-23)
                            // x.getPixelForValue(index)
                            startX = x.getPixelForValue(Math.floor(startHour));
                            endX = x.getPixelForValue(Math.ceil(endHour));
                        }
                    } else if (mode === 'weekly') { // Weekly logic handled differently (per bar), so return here
                    } else if (mode === 'monthly') {
                        // Labels 1-31
                        const monthYear = new Date(filterValue);

                        // Loop setiap tanggal di bulan itu
                        labels.forEach((lbl, index) => {
                            const dateNum = parseInt(lbl);
                            const currentDayDate = new Date(monthYear.getFullYear(), monthYear.getMonth(), dateNum);
                            const nextDayDate = new Date(monthYear.getFullYear(), monthYear.getMonth(), dateNum + 1);

                            // Cek overlap
                            // (Start < DayEnd) AND (End > DayStart)
                            if (start < nextDayDate && end > currentDayDate) {
                                const xPos = x.getPixelForValue(index); // Index array, bukan tanggal
                                const barWidth = width / labels.length;

                                ctx.fillStyle = 'rgba(239, 68, 68, 0.15)';
                                ctx.fillRect(xPos - (barWidth / 2), top, barWidth, height);
                            }
                        });
                        return;
                    }

                    // Gambar Rect (Khusus Daily yang kontinyu)
                    if (startX !== null && endX !== null) {
                        const rectWidth = endX - startX;

                        // Background Merah Transparan
                        ctx.fillStyle = 'rgba(239, 68, 68, 0.15)'; // Red-500 low opacity
                        ctx.fillRect(startX, top, rectWidth, height);

                        // Label "MAINTENANCE"
                        ctx.fillStyle = '#DC2626'; // Red-600
                        ctx.font = 'bold 10px sans-serif';
                        ctx.fillText('MAINTENANCE', startX + 5, top + 15);
                        ctx.font = '9px sans-serif';
                        ctx.fillText(maint.title.substring(0, 15) + '...', startX + 5, top + 25);
                    }
                });

                ctx.restore();
            }
        };

        // Plugin Inline Label Angka (Floating)
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
                        if (dataValue > 0) {
                            ctx.font = 'bold 12px sans-serif';
                            ctx.fillStyle = `rgba(${colorBase}, 1)`;
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(dataValue, element.x, element.y - 8);
                        }
                    });
                });
                ctx.restore();
            }
        };

        myChart = new Chart(ctx, {
            type: 'line',
            plugins: [floatingLabelsPlugin, maintenancePlugin], // Daftarkan plugin
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Aktivitas',
                    data: dataPoints,
                    backgroundColor: bgColor,
                    borderColor: borderColor,
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: borderColor,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 20
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
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
    function loadLogs(page = 1) {
        if (typeof page !== 'number' || isNaN(page)) page = 1;
        currentPage = page;
        const search = document.getElementById('search-log').value;
        const type = document.getElementById('filter-type').value;
        const container = document.getElementById('log-container');

        const formData = new FormData();
        formData.append('action', 'get_logs');
        formData.append('search', search);
        formData.append('type', type);
        formData.append('page', page);

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
                    renderPagination(0, 0, 0);
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
                    else if (log.action_type == 'EXPORT') badgeClass = 'bg-orange-100 text-orange-800';
                    else if (log.action_type == 'MAINTENANCE') badgeClass = 'bg-cyan-100 text-cyan-800';
                    const formattedMessage = formatLogText(log.description);
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
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass}">${log.action_type}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-700 break-words align-top leading-relaxed">${formattedMessage}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-gray-400 font-mono align-top">
                        <div class="mb-1">IP: <span class="text-gray-600">${log.ip_address}</span></div>
                        <div class="truncate max-w-[200px] inline-block cursor-help border-b border-dotted border-gray-300" title="${log.user_agent}">${log.user_agent ? log.user_agent : '-'}</div>
                    </td>
                </tr>`;
                });
                container.innerHTML = html;
                renderPagination(data.pagination.total_pages, data.pagination.total_records, data.data.length);
            });
    }

    function renderPagination(totalPages, totalRecords, currentCount) {
        const limit = 20;
        const pageNum = parseInt(currentPage) || 1;
        const totalRec = parseInt(totalRecords) || 0;
        const currCnt = parseInt(currentCount) || 0;
        const start = (pageNum - 1) * limit + 1;
        const end = start + currCnt - 1;
        document.getElementById('page-info-start').innerText = totalRec > 0 ? start : 0;
        document.getElementById('page-info-end').innerText = totalRec > 0 ? end : 0;
        document.getElementById('page-info-total').innerText = totalRec;
        document.getElementById('btn-prev').disabled = (pageNum <= 1);
        document.getElementById('btn-next').disabled = (pageNum >= totalPages);
    }

    function changePage(direction) {
        const newPage = currentPage + direction;
        loadLogs(newPage);
    }

    function clearAllLogs() {
        if (!confirm('Hapus seluruh riwayat?')) return;
        const formData = new FormData();
        formData.append('action', 'clear_logs');
        fetch(API_LOG, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    alert('Log dibersihkan.');
                    loadLogs(1);
                    loadChartData();
                }
            });
    }

    document.getElementById('search-log').addEventListener('keyup', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadLogs(1), 500);
    });
    document.getElementById('filter-type').addEventListener('change', () => {
        loadLogs(1);
        loadChartData();
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadLogs(1);
        loadChartData();
    });
</script>