<?php
// modules/dashboard/admin.php

// Load configuration and dependencies
include_once __DIR__ . '/../../config/constants.php';
include_once __DIR__ . '/../../config/database.php';
include_once __DIR__ . '/../../includes/session.php';
include_once __DIR__ . '/../../includes/functions.php';
include_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_ADMIN);

// Dashboard logic
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStock = $pdo->query("SELECT SUM(current_stock) FROM cylinder_types")->fetchColumn();
$totalSales = $pdo->query("SELECT SUM(total_amount) FROM sales")->fetchColumn();
$totalExpenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn();
$totalDebts = $pdo->query("SELECT SUM(balance) FROM debts WHERE balance > 0")->fetchColumn();


// Handle null values safely
$totalUsers = $totalUsers ?? 0;
$totalStock = $totalStock ?? 0;
$totalSales = $totalSales ?? 0;
$totalExpenses = $totalExpenses ?? 0;
$totalDebts = $totalDebts ?? 0;

$profit = $totalSales - $totalExpenses;
$username = $_SESSION['username'] ?? 'Admin'; // Handle undefined username
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | GIMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
        .sidebar-shadow { box-shadow: 5px 0 25px rgba(0,0,0,0.3); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background-color: --primary-color: #FF6B35; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
        .active-menu-item { background: #004E89}
        .hover-gradient { background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, rgba(0, 0, 0, 0) 100%); }
        .pulse-animation { animation: pulse 2s infinite; }
        .glow-effect { box-shadow: 0 0 15px rgba(99, 102, 241, 0.3); }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .dropdown-menu {
            min-width: 10rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-poppins">
    <!-- Top Navigation Bar -->
    <nav class="fixed top-0 right-0 left-0 lg:left-0 bg-white shadow-sm z-30 transition-all duration-300">
        <div class="flex justify-between items-center px-4 py-3">
            <div class="lg:hidden">
                <button class="sidebar-toggle text-gray-600 hover:text-gray-900 focus:outline-none" id="sidebarToggle">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button class="flex items-center space-x-2 focus:outline-none" id="userDropdown">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                            <i class="fas fa-user text-indigo-600"></i>
                        </div>
                        <span class="hidden md:inline-block font-medium"><?= htmlspecialchars($username) ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden" id="dropdownMenu">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user-circle mr-2"></i> View Profile
                        </a>
                        <a href="<?= BASE_URL ?>modules/auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar fixed left-0 top-0 h-screen w-80 text-white z-40 sidebar-shadow transition-all duration-500 ease-i n-out transform -translate-x-full lg:translate-x-0" id="sidebar" style="background-color:--primary-color: #FF6B35 ;">
        <!-- Sidebar Header -->
        <div class="sidebar-header p-6 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-900">
            <div class="logo flex items-center mb-6 animate-fade-in">
                <div class="icon-container bg-indigo-600 rounded-lg p-3 mr-4 shadow-lg transform hover:rotate-12 transition-transform duration-300">
                    
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-wide">GIMS</h1>
                    
                </div>
            </div>
            
        </div>
        
        <!-- Sidebar Menu -->
        <div class="sidebar-menu flex-1 overflow-y-auto py-4 custom-scrollbar">
            <!-- Dashboard -->
            <div class="menu-section mb-2 px-4 style">
                <a href="<?= BASE_URL ?>modules/dashboard/admin.php" class="menu-item flex items-center px-4 py-3 rounded-lg text-white bg-gray-750 transition-all duration-300 group relative overflow-hidden active-menu-item">
                    <div class="icon-container bg-indigo-600 rounded-lg p-2 mr-4 transition-colors duration-300">
                        <i class="fas fa-tachometer-alt text-lg text-white"></i>
                    </div>
                    <span class="font-medium">Dashboard</span>
                    <div class="active-indicator absolute right-4 w-2 h-2 bg-green-400 rounded-full"></div>
                    <div class="hover-effect absolute inset-0 hover-gradient opacity-100 transition-opacity duration-500"></div>
                </a>
            </div>

            <!-- Stock Management -->
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-boxes text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Stock</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-plus-circle mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Add Stock</span>
                        </a>
                        <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-eye mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Stock</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sales -->
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-cash-register text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Sales</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/sales/record_sale.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-hand-holding-usd mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Record Sale</span>
                        </a>
                        <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-list-ol mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Sales</span>
                        </a>
                        <a href="<?= BASE_URL ?>modules/sales/sales_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-list-ol mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Sales Report</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Debts -->
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-hand-holding-usd text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Debts</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-list mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Debts</span>
                        </a>
                        <a href="<?= BASE_URL ?>modules/debts/record_payment.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-money-bill-wave mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Record Payment</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content ml-0 lg:ml-80 transition-all duration-300 pt-16">
        <div class="p-6 md:p-8">
            <!-- Dashboard Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Dashboard</h1>
                    <p class="text-gray-600">Welcome back, <?= htmlspecialchars($username) ?>!</p>
                </div>
                <div class="mt-4 md:mt-0">
                    <span class="inline-block bg-indigo-100 text-indigo-800 text-sm font-semibold px-3 py-1 rounded-full">
                        <i class="fas fa-calendar-day mr-1"></i>
                        <?= date('l, F j, Y') ?>
                    </span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Users Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600 mr-4">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Users</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($totalUsers) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stock Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-gas-pump text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Cylinders in Stock</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($totalStock) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                

                <!-- Debts Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                                <i class="fas fa-hand-holding-usd text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Debts</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= number_format((float)$totalDebts, 2) ?> TZS</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-chart-line text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Sales</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= number_format((float)$totalSales, 2) ?> TZS</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                <i class="fas fa-receipt text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Expenses</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= number_format((float)$totalExpenses, 2) ?> TZS</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-teal-100 text-teal-600 mr-4">
                                <i class="fas fa-coins text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Total Profit</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= number_format((float)$profit, 2) ?> TZS</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profit Margin Card -->
                <div class="stat-card bg-white rounded-xl shadow-md overflow-hidden transition-all duration-300 hover:glow-effect">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-pink-100 text-pink-600 mr-4">
                                <i class="fas fa-percentage text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium">Profit Margin</p>
                                <h3 class="text-2xl font-bold text-gray-800">
                                    <?= $totalSales > 0 ? number_format(($profit / $totalSales) * 100, 2) : 0 ?>%
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Statistics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-500 text-sm font-medium mb-1">Average Sale</p>
                                <h4 class="text-xl font-bold text-gray-800">
                                    <?= $totalUsers > 0 ? number_format($totalSales / $totalUsers, 2) : 0 ?> TZS
                                </h4>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-user mr-1"></i> Per User
                                    </span>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-500 text-sm font-medium mb-1">Stock Value</p>
                                <h4 class="text-xl font-bold text-gray-800">
                                    <?= number_format($totalStock * 50000, 2) ?> TZS
                                </h4>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-box mr-1"></i> Estimated
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="<?= BASE_URL ?>modules/sales/record_sale.php" class="flex items-center p-3 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition-colors duration-200">
                                <i class="fas fa-plus-circle mr-3"></i>
                                <span>Record New Sale</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition-colors duration-200">
                                <i class="fas fa-box-open mr-3"></i>
                                <span>Add Stock</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/gas_supply/add_supply.php" class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors duration-200">
                                <i class="fas fa-truck mr-3"></i>
                                <span>Add Gas Supply</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar on mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 1024) {
                if (!sidebar.contains(e.target) ){
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('translate-x-0');
                }
            }
        });

        // User dropdown menu
        const userDropdown = document.getElementById('userDropdown');
        const dropdownMenu = document.getElementById('dropdownMenu');
        
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdownMenu.classList.add('hidden');
        });

        // Accordion functionality for menu sections
        const menuHeadings = document.querySelectorAll('.menu-heading');
        
        menuHeadings.forEach(heading => {
            heading.addEventListener('click', function() {
                const submenu = this.nextElementSibling;
                const isOpen = submenu.style.maxHeight && submenu.style.maxHeight !== '0px';
                
                // Toggle current submenu
                if (isOpen) {
                    submenu.style.maxHeight = '0';
                    this.classList.remove('active');
                } else {
                    submenu.style.maxHeight = submenu.scrollHeight + 'px';
                    this.classList.add('active');
                }
            });
        });

        // Add animation to stats cards on load
        const statsCards = document.querySelectorAll('.stat-card');
        statsCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `all 0.5s ease ${index * 0.1}s`;
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    });
    </script>
</body>
</html>