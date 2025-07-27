<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
if (!hasRole('owner') && !hasRole('admin')) {
    header("Location: ../dashboard.php");
    exit();
}

$user = getUserInfo();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan Real-time - Ramen App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-chart-line text-success me-2"></i>
                        Dashboard Keuangan Real-time
                        <span class="live-indicator ms-2"></span>
                    </h2>
                    <div>
                        <button class="btn btn-primary" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <a href="financial.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="todayRevenue">Rp 0</div>
                    <div class="metric-label">Pendapatan Hari Ini</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="todayTransactions">0</div>
                    <div class="metric-label">Transaksi Hari Ini</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="avgTransaction">Rp 0</div>
                    <div class="metric-label">Rata-rata Transaksi</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="successRate">0%</div>
                    <div class="metric-label">Tingkat Keberhasilan</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area text-primary me-2"></i>
                            Pendapatan Per Jam (Hari Ini)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-star text-warning me-2"></i>
                            Top Menu Hari Ini
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="topMenuList">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Revenue Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line text-success me-2"></i>
                            Tren Pendapatan 7 Hari Terakhir
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 400px;">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let hourlyChart, weeklyChart;
        
        // Initialize charts
        function initCharts() {
            // Hourly Chart
            const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            hourlyChart = new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pendapatan',
                        data: [],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });

            // Weekly Chart
            const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
            weeklyChart = new Chart(weeklyCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pendapatan',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Load data functions
        async function loadSummary() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`financial_api.php?action=summary&date_from=${today}&date_to=${today}`);
                const data = await response.json();
                
                document.getElementById('todayRevenue').textContent = 'Rp ' + parseInt(data.total_pendapatan || 0).toLocaleString('id-ID');
                document.getElementById('todayTransactions').textContent = data.total_transaksi || 0;
                document.getElementById('avgTransaction').textContent = 'Rp ' + parseInt(data.rata_rata || 0).toLocaleString('id-ID');
                
                const successRate = data.total_transaksi > 0 ? (data.transaksi_sukses / data.total_transaksi * 100) : 0;
                document.getElementById('successRate').textContent = successRate.toFixed(1) + '%';
            } catch (error) {
                console.error('Error loading summary:', error);
            }
        }

        async function loadHourlyData() {
            try {
                const response = await fetch('financial_api.php?action=hourly_revenue');
                const data = await response.json();
                
                const hours = Array.from({length: 24}, (_, i) => i.toString().padStart(2, '0') + ':00');
                const revenues = new Array(24).fill(0);
                
                data.forEach(item => {
                    revenues[parseInt(item.jam)] = parseInt(item.pendapatan);
                });
                
                hourlyChart.data.labels = hours;
                hourlyChart.data.datasets[0].data = revenues;
                hourlyChart.update();
            } catch (error) {
                console.error('Error loading hourly data:', error);
            }
        }

        async function loadWeeklyData() {
            try {
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(endDate.getDate() - 6);
                
                const response = await fetch(`financial_api.php?action=daily_revenue&date_from=${startDate.toISOString().split('T')[0]}&date_to=${endDate.toISOString().split('T')[0]}`);
                const data = await response.json();
                
                const labels = [];
                const revenues = [];
                
                for (let i = 6; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    const dateStr = date.toISOString().split('T')[0];
                    labels.push(date.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' }));
                    
                    const dayData = data.find(item => item.tanggal === dateStr);
                    revenues.push(dayData ? parseInt(dayData.pendapatan) : 0);
                }
                
                weeklyChart.data.labels = labels;
                weeklyChart.data.datasets[0].data = revenues;
                weeklyChart.update();
            } catch (error) {
                console.error('Error loading weekly data:', error);
            }
        }

        async function loadTopMenu() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await fetch(`financial_api.php?action=top_menu&date_from=${today}&date_to=${today}`);
                const data = await response.json();
                
                let html = '';
                if (data.length === 0) {
                    html = '<p class="text-muted text-center">Belum ada data hari ini</p>';
                } else {
                    data.slice(0, 5).forEach((item, index) => {
                        html += `
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                <div>
                                    <span class="badge bg-primary me-2">${index + 1}</span>
                                    <strong>${item.nama_menu}</strong>
                                    <br><small class="text-muted">${item.nama_kategori}</small>
                                </div>
                                <div class="text-end">
                                    <div><strong>Rp ${parseInt(item.total_pendapatan).toLocaleString('id-ID')}</strong></div>
                                    <small class="text-muted">${item.total_terjual}x terjual</small>
                                </div>
                            </div>
                        `;
                    });
                }
                
                document.getElementById('topMenuList').innerHTML = html;
            } catch (error) {
                console.error('Error loading top menu:', error);
                document.getElementById('topMenuList').innerHTML = '<p class="text-danger text-center">Error loading data</p>';
            }
        }

        function refreshData() {
            loadSummary();
            loadHourlyData();
            loadWeeklyData();
            loadTopMenu();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            refreshData();
            
            // Auto refresh every 30 seconds
            setInterval(refreshData, 30000);
        });
    </script>
</body>
</html>
