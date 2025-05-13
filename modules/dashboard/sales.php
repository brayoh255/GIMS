<?php
// modules/dashboard/sales.php

// Load configuration and dependencies
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Role check
checkRole(ROLE_SALES);
// Get current user data
$userId = getCurrentUserId();
$username = $_SESSION['username'] ?? 'Salesperson';

// Check if we're coming from a successful sale recording
$saleRecorded = isset($_SESSION['sale_recorded']);
if ($saleRecorded) {
    unset($_SESSION['sale_recorded']);
}

// Fetch recent transactions
try {
    // Recent Transactions (last 5)
    $stmt = $pdo->prepare("SELECT s.id, s.total_amount, s.created_at, c.name as customer_name 
                          FROM sales s 
                          LEFT JOIN customers c ON s.customer_id = c.id 
                          WHERE s.created_by = ? 
                          ORDER BY s.created_at DESC 
                          LIMIT 5");
    $stmt->execute([$userId]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Sales Dashboard Error: " . $e->getMessage());
    $recentTransactions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard | GIMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #FF6B35;
            --primary-light: #ff9e35;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --dark: #1a1a1a;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #ef233c;
            --info: #7209b7;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            overflow-x: hidden;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 900;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary);
            margin-right: 20px;
        }

        .logo span {
            color: var(--dark);
        }

        .user-dropdown {
            margin-left: auto;
            position: relative;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
            padding: 10px 0;
            margin-top: 10px;
        }

        .dropdown-item {
            padding: 8px 15px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background-color: rgba(255, 107, 53, 0.1);
            color: var(--primary);
        }

        .dropdown-divider {
            margin: 5px 0;
            border-color: rgba(0,0,0,0.05);
        }

        /* Navigation Cards */
        .nav-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 20px;
            background: white;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .nav-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            z-index: 1;
        }

        .nav-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
        }

        .nav-card .card-body {
            position: relative;
            z-index: 2;
            padding: 25px;
        }

        .nav-card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: white;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            margin-left: auto;
            margin-right: auto;
            transition: all 0.3s ease;
        }

        .nav-card:hover .nav-card-icon {
            transform: rotate(10deg) scale(1.1);
        }

        .nav-card-title {
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
            text-align: center;
        }

        .nav-card-text {
            color: rgba(255,255,255,0.8);
            text-align: center;
            font-size: 0.9rem;
        }

        /* Recent Transactions */
        .recent-transactions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .recent-transactions:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .recent-transactions th {
            border-top: none;
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .transaction-row:hover {
            background-color: rgba(255, 107, 53, 0.05);
        }

        /* Main content */
        .main-content {
            padding-top: 90px;
            padding-bottom: 40px;
        }

        /* Color classes for nav cards */
        .nav-card-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }
        .nav-card-success {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
        }
        .nav-card-warning {
            background: linear-gradient(135deg, #f72585, #b5179e);
        }
        .nav-card-info {
            background: linear-gradient(135deg, #7209b7, #560bad);
        }

        /* Welcome card */
        .welcome-card {
            border: none;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="logo">GIMS </div>
        
        <div style="flex: 1;"></div>
        
        <div class="user-dropdown">
            <button class="btn dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="display: flex; align-items: center;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px; font-weight: 600;">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>
                <span style="font-weight: 500;"><?= htmlspecialchars($username) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>modules/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Welcome Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="welcome-card animate__animated animate__fadeIn">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 20px; font-size: 1.5rem; font-weight: 600;">
                                    <?= strtoupper(substr($username, 0, 1)) ?>
                                </div>
                                <div>
                                    <h2 class="mb-1">Welcome back, <?= htmlspecialchars($username) ?>!</h2>
                                    <p class="text-muted mb-0">Ready to make some sales today?</p>
                                </div>
                                <div class="ms-auto">
                                    <span class="badge bg-primary bg-opacity-10 text-primary py-2 px-3">
                                        <i class="fas fa-calendar-day me-2"></i>
                                        <?= date('l, F j, Y') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3 animate__animated animate__fadeIn animate-delay-1">
                    <a href="<?= BASE_URL ?>modules/expense/sales.php" class="text-decoration-none">
                        <div class="nav-card nav-card-primary">
                            <div class="card-body text-center">
                                <div class="nav-card-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <h4 class="nav-card-title">Dashboard</h4>
                                <p class="nav-card-text">Your sales overview and analytics</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-6 col-lg-3 animate__animated animate__fadeIn animate-delay-2">
                    <a href="<?= BASE_URL ?>modules/sales/sales_record.php" class="text-decoration-none">
                        <div class="nav-card nav-card-success">
                            <div class="card-body text-center">
                                <div class="nav-card-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <h4 class="nav-card-title">Record Sale</h4>
                                <p class="nav-card-text">Create new sales transactions</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-6 col-lg-3 animate__animated animate__fadeIn animate-delay-3">
                    <a href="<?= BASE_URL ?>modules/sales/sales_view.php" class="text-decoration-none">
                        <div class="nav-card nav-card-warning">
                            <div class="card-body text-center">
                                <div class="nav-card-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h4 class="nav-card-title">Sales History</h4>
                                <p class="nav-card-text">View past transactions</p>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-6 col-lg-3 animate__animated animate__fadeIn animate-delay-4">
                    <a href="<?= BASE_URL ?>modules/debts/sales.php" class="text-decoration-none">
                        <div class="nav-card nav-card-info">
                            <div class="card-body text-center">
                                <div class="nav-card-icon">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <h4 class="nav-card-title">Customer Debts</h4>
                                <p class="nav-card-text">Manage pending customer debts</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
           

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover animations to nav cards
            const navCards = document.querySelectorAll('.nav-card');
            navCards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    gsap.to(card, {
                        y: -8,
                        scale: 1.02,
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
                
                card.addEventListener('mouseleave', () => {
                    gsap.to(card, {
                        y: 0,
                        scale: 1,
                        duration: 0.3,
                        ease: "power2.out"
                    });
                });
            });

            // Function to update recent transactions
            async function updateRecentTransactions() {
                try {
                    const response = await fetch('<?= BASE_URL ?>modules/api/recent_transactions.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        const tbody = document.querySelector('#transactionsTable tbody');
                        
                        // Animate out old rows
                        gsap.to(tbody.querySelectorAll('tr'), {
                            opacity: 0,
                            y: -20,
                            duration: 0.3,
                            stagger: 0.05,
                            onComplete: () => {
                                tbody.innerHTML = '';
                                
                                if (data.data.length > 0) {
                                    data.data.forEach((transaction, index) => {
                                        const row = document.createElement('tr');
                                        row.className = 'transaction-row';
                                        row.style.opacity = '0';
                                        row.style.transform = 'translateY(-20px)';
                                        row.innerHTML = `
                                            <td>${new Date(transaction.created_at).toLocaleString('en-US', {
                                                month: 'short',
                                                day: 'numeric',
                                                year: 'numeric',
                                                hour: 'numeric',
                                                minute: 'numeric',
                                                hour12: true
                                            })}</td>
                                            <td>${transaction.customer_name || 'Walk-in'}</td>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary">${parseFloat(transaction.total_amount).toFixed(2)} TZS</span></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>modules/sales/sale_details.php?id=${transaction.id}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            </td>
                                        `;
                                        tbody.appendChild(row);
                                        
                                        // Animate in new rows with stagger
                                        gsap.to(row, {
                                            opacity: 1,
                                            y: 0,
                                            duration: 0.3,
                                            delay: index * 0.05
                                        });
                                    });
                                } else {
                                    tbody.innerHTML = `
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <div class="py-4">
                                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No recent transactions found</h5>
                                                    <p class="text-muted mb-0">Record your first sale today!</p>
                                                    <a href="<?= BASE_URL ?>modules/sales/sales_record.php" class="btn btn-primary mt-3">
                                                        <i class="fas fa-plus me-1"></i> Record Sale
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error updating transactions:', error);
                }
            }

            // Refresh button click handler
            document.getElementById('refreshTransactions').addEventListener('click', function() {
                // Add spin animation to refresh icon
                const icon = this.querySelector('i');
                icon.classList.add('fa-spin');
                
                updateRecentTransactions();
                
                // Remove spin animation after 1 second
                setTimeout(() => {
                    icon.classList.remove('fa-spin');
                }, 1000);
            });

            // Update transactions periodically (every 60 seconds)
            setInterval(updateRecentTransactions, 60000);

            // If we came from a successful sale, update transactions immediately
            <?php if ($saleRecorded): ?>
                // Add celebration animation
                gsap.from(".nav-card", {
                    y: 50,
                    opacity: 0,
                    duration: 0.8,
                    stagger: 0.1,
                    ease: "back.out(1.7)"
                });
                
                updateRecentTransactions();
            <?php endif; ?>
        });
    </script>
</body>
</html>