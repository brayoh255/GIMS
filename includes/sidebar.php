<div class="sidebar-wrapper">
    <!-- Toggle Button -->
    <button class="sidebar-toggle fixed left-4 top-4 z-50 bg-indigo-600 text-white rounded-full w-10 h-10 flex items-center justify-center shadow-lg hover:bg-indigo-700 transition-all duration-300 hover:scale-110 lg:hidden" id="sidebarToggle">
        <i class="fas fa-bars text-lg"></i>
    </button>
    
    <div class="sidebar fixed left-0 top-0 h-screen w-80 bg-gradient-to-b from-gray-900 to-gray-800 text-white z-40 shadow-2xl transition-all duration-500 ease-in-out transform -translate-x-full lg:translate-x-0" id="sidebar">
        <!-- Sidebar Header with GIMS Logo -->
        <div class="sidebar-header p-6 border-b border-gray-700 bg-gradient-to-r from-gray-800 to-gray-900">
            <div class="logo flex items-center mb-6 animate-fade-in">
                <div class="icon-container bg-indigo-600 rounded-lg p-3 mr-4 shadow-lg transform hover:rotate-12 transition-transform duration-300">
                    <i class="fas fa-gas-pump text-2xl text-yellow-400"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white font-poppins tracking-wide">GIMS</h1>
    
                </div>
            </div>
            <div class="user-info flex items-center bg-gray-800 rounded-lg p-3 hover:bg-gray-750 transition-colors duration-300">
                <div class="user-avatar relative">
                    <i class="fas fa-user-circle text-3xl text-gray-300"></i>
                    <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-800"></span>
                </div>
                <div class="user-details ml-3">
                    <span class="user-role bg-indigo-600 text-white text-xs px-3 py-1 rounded-full inline-block transform hover:scale-105 transition-transform duration-200">
                        <?= ucfirst($_SESSION['role']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Menu with Perfectly Arranged Items -->
        <div class="sidebar-menu flex-1 overflow-y-auto py-4 custom-scrollbar">
            <!-- Dashboard -->
            <div class="menu-section mb-2 px-4">
                <a href="<?= $_SESSION['role'] == ROLE_ADMIN ? BASE_URL.'modules/dashboard/admin.php' : 
                          ($_SESSION['role'] == ROLE_MANAGER ? BASE_URL.'modules/dashboard/manager.php' : 
                          BASE_URL.'modules/dashboard/sales.php') ?>" 
                   class="menu-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:text-white hover:bg-gray-750 transition-all duration-300 group relative overflow-hidden">
                    <div class="icon-container bg-gray-800 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                        <i class="fas fa-tachometer-alt text-lg text-yellow-400"></i>
                    </div>
                    <span class="font-medium">Dashboard</span>
                    <div class="active-indicator absolute right-4 w-2 h-2 bg-green-400 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="hover-effect absolute inset-0 bg-gradient-to-r from-indigo-900/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                </a>
            </div>

            <!-- Stock Management -->
            <?php if ($_SESSION['role'] == ROLE_ADMIN || $_SESSION['role'] == ROLE_MANAGER): ?>
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-boxes text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Stock</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                        <a href="<?= BASE_URL ?>modules/inventory/add_stock.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-plus-circle mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Add Stock</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>modules/inventory/view_stock.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-eye mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Stock</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/inventory/update_stock.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-edit mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Update Stock</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sales -->
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-cash-register text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Sales</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/sales/record_sale.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-hand-holding-usd mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Record Sale</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/sales/view_sales.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-list-ol mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Sales</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/sales/sales_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-chart-line mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Sales Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Expenses -->
            <?php if ($_SESSION['role'] == ROLE_ADMIN || $_SESSION['role'] == ROLE_MANAGER): ?>
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-receipt text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Expenses</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                        <a href="<?= BASE_URL ?>modules/expenses/record_expense.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-plus-circle mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Add Expense</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>modules/expenses/view_expenses.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-file-invoice-dollar mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Expenses</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/expenses/expense_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-chart-pie mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Expense Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Debts -->
            <?php if ($_SESSION['role'] == ROLE_ADMIN || $_SESSION['role'] == ROLE_MANAGER || $_SESSION['role'] == ROLE_SALES): ?>
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-hand-holding-usd text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Debts</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/debts/view_debts.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-list mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Debts</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/debts/record_payment.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-money-bill-wave mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Record Payment</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/debts/debtors_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-file-invoice mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Debt Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Reports -->
            <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-chart-bar text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Reports</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <a href="<?= BASE_URL ?>modules/reports/daily_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-calendar-day mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Daily Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/reports/inventory_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-boxes mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Inventory Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/reports/financial_report.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-file-invoice-dollar mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Financial Report</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customers -->
            <?php if ($_SESSION['role'] == ROLE_ADMIN || $_SESSION['role'] == ROLE_MANAGER): ?>
            <div class="menu-section mb-2 px-4">
                <div class="menu-heading flex items-center justify-between px-4 py-3 rounded-lg bg-gray-800 cursor-pointer hover:bg-gray-750 transition-colors duration-300 group">
                    <div class="flex items-center">
                        <div class="icon-container bg-gray-700 rounded-lg p-2 mr-4 group-hover:bg-indigo-600 transition-colors duration-300">
                            <i class="fas fa-users text-lg text-yellow-400"></i>
                        </div>
                        <span class="font-medium">Customers</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300 transform group-[.active]:rotate-180"></i>
                </div>
                <div class="submenu overflow-hidden transition-all duration-500 ease-in-out max-h-0">
                    <div class="py-1 pl-14 pr-4">
                        <?php if ($_SESSION['role'] == ROLE_ADMIN): ?>
                        <a href="<?= BASE_URL ?>modules/customers/add_customer.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-user-plus mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Add Customer</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>modules/customers/view_customers.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-address-book mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">View Customers</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="<?= BASE_URL ?>modules/customers/customer_debts.php" class="submenu-item flex items-center px-3 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-gray-750 transition-colors duration-300 mb-1 last:mb-0 group">
                            <i class="fas fa-file-invoice mr-3 text-xs text-indigo-400 group-hover:text-yellow-400 transition-colors duration-300"></i>
                            <span class="text-sm">Customer Debts</span>
                            <div class="ml-auto w-1.5 h-1.5 bg-indigo-600 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Logout Button -->
        <div class="sidebar-footer p-4 border-t border-gray-700 bg-gradient-to-r from-gray-800 to-gray-900">
            <a href="<?= BASE_URL ?>modules/auth/logout.php" class="logout-btn flex items-center justify-center px-4 py-2 rounded-lg text-red-400 hover:text-white hover:bg-red-600/20 transition-colors duration-300 border border-red-600/30 hover:border-red-600/50">
                <i class="fas fa-sign-out-alt mr-2"></i>
                <span class="font-medium">Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Include Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Include Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
/* Custom animations and effects */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-out forwards;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.animate-pulse {
    animation: pulse 2s infinite;
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.2);
}

/* Active menu item styles */
.menu-item.active, .submenu-item.active {
    background: rgba(79, 70, 229, 0.15);
    color: white;
    border-left: 3px solid #4f46e5;
}

.menu-item.active .icon-container, .submenu-item.active .icon-container {
    background-color: #4f46e5 !important;
}

.menu-item.active i, .submenu-item.active i {
    color: white !important;
}

/* Hover effects */
.hover-effect {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, rgba(0, 0, 0, 0) 100%);
}

/* Responsive adjustments */
@media (max-width: 1023px) {
    .sidebar {
        box-shadow: 5px 0 25px rgba(0,0,0,0.3);
    }
}

/* Smooth transitions */
.submenu {
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Icon container animation */
.icon-container {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Active indicator for submenu items */
.submenu-item .active-indicator {
    transition: all 0.3s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 1024) {
            if (!sidebar.contains(e.target) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
            }
        }
    });

    // Accordion functionality for menu sections
    const menuHeadings = document.querySelectorAll('.menu-heading');
    
    menuHeadings.forEach(heading => {
        heading.addEventListener('click', function() {
            const submenu = this.nextElementSibling;
            const isOpen = submenu.style.maxHeight && submenu.style.maxHeight !== '0px';
            
            // Close all other submenus
            document.querySelectorAll('.submenu').forEach(sm => {
                if (sm !== submenu) {
                    sm.style.maxHeight = '0';
                    sm.previousElementSibling.classList.remove('active');
                }
            });
            
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

    // Set active menu item based on current page
    function setActiveMenu() {
        const currentPath = window.location.pathname;
        const menuItems = document.querySelectorAll('.menu-item, .submenu-item');
        
        menuItems.forEach(item => {
            if (item.getAttribute('href') && currentPath.includes(item.getAttribute('href'))) {
                item.classList.add('active');
                
                // Expand parent menu if this is a submenu item
                if (item.classList.contains('submenu-item')) {
                    const parentMenu = item.closest('.menu-section');
                    if (parentMenu) {
                        const heading = parentMenu.querySelector('.menu-heading');
                        const submenu = parentMenu.querySelector('.submenu');
                        if (heading && submenu) {
                            heading.classList.add('active');
                            submenu.style.maxHeight = submenu.scrollHeight + 'px';
                        }
                    }
                }
            }
        });
    }

    setActiveMenu();

    // Add hover effects programmatically
    const menuItems = document.querySelectorAll('.menu-item, .submenu-item');
    menuItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.querySelector('.hover-effect').style.opacity = '1';
        });
        
        item.addEventListener('mouseleave', function() {
            this.querySelector('.hover-effect').style.opacity = '0';
        });
    });

    // Initialize first menu section as open by default
    if (menuHeadings.length > 0) {
        menuHeadings[0].click();
    }
});
</script>