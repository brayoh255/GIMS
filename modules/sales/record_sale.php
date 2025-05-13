<?php
require_once '../../config/database.php';
session_start();

// Fetch all available products (quantity > 0) with their current stock
$products = [];
try {
    $stmt = $pdo->query("SELECT id, item_name, price, quantity, size FROM inventory WHERE quantity > 0 ORDER BY item_name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to fetch products: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']); // New field
    $created_by = $_SESSION['user_id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // 1. Create sale record (updated to include phone)
        $stmt = $pdo->prepare("INSERT INTO sales (customer_name, customer_phone, created_by, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$customer_name, $customer_phone, $created_by]);
        $sale_id = $pdo->lastInsertId();

        // 2. Process each product
        $total_sale_amount = 0;
        
        foreach ($_POST['products'] as $productData) {
            $product_id = (int)$productData['id'];
            $quantity = (int)$productData['quantity'];
            
            // Verify product exists and has sufficient stock
            $stmt = $pdo->prepare("SELECT item_name, price, quantity FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Product not found or out of stock");
            }

            if ($product['quantity'] < $quantity) {
                throw new Exception("Insufficient stock for {$product['item_name']}. Available: {$product['quantity']}");
            }

            $price = (float)$product['price'];
            $total_amount = $quantity * $price;
            $total_sale_amount += $total_amount;

            // Add to sale items
            $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, quantity, price, total_amount)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $sale_id,
                $product_id,
                $product['item_name'],
                $quantity,
                $price,
                $total_amount
            ]);

            // Update inventory
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $product_id]);
        }

        // 3. Update sale with total amount
        $amount_paid = (float)$_POST['amount_paid'];
        $stmt = $pdo->prepare("UPDATE sales SET total_amount = ?, amount_paid = ? WHERE id = ?");
        $stmt->execute([$total_sale_amount, $amount_paid, $sale_id]);

        // 4. Record debt if payment is less than total (updated to include phone)
        if ($amount_paid < $total_sale_amount) {
            $balance = $total_sale_amount - $amount_paid;
            $stmt = $pdo->prepare("INSERT INTO debts (customer_name, customer_phone, sale_id, amount, paid_amount, balance)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $customer_name,
                $customer_phone,
                $sale_id,
                $total_sale_amount,
                $amount_paid,
                $balance
            ]);
        }

        $pdo->commit();
        
        $_SESSION['success'] = "Sale recorded successfully! Total: " . number_format($total_sale_amount, 2);
        if ($amount_paid < $total_sale_amount) {
            $_SESSION['success'] .= ". Outstanding balance: " . number_format($total_sale_amount - $amount_paid, 2);
        }
        header("Location: view_sales.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record New Sale</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .error { color: red; padding: 10px; margin-bottom: 15px; background: #ffeeee; }
        .success { color: green; padding: 10px; margin-bottom: 15px; background: #eeffee; }
        .form-group { margin-bottom: 15px; }
        label { display: inline-block; width: 150px; }
        input, select { padding: 8px; width: 250px; }
        button { padding: 10px 15px; color: white; border: none; cursor: pointer; }
        .product-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .product-select { flex: 2; }
        .quantity-input { flex: 1; }
        .stock-info { flex: 1; color: #666; }
        .remove-btn { flex: 0 0 30px; background: #f44336; }
        .add-btn { margin: 10px 0 20px 160px; background: #2196F3; }
        .submit-btn { background: #4CAF50; }
        #summary { margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px; }
        .stock-label { color: #2196F3; font-weight: bold; }
        .balance-warning { color: #ff9800; font-weight: bold; display: none; }
    </style>
</head>
<body>
    <h2>Record New Sale</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="post" id="sale-form">
        <div class="form-group">
            <label for="customer_name">Customer Name:</label>
            <input type="text" name="customer_name" id="customer_name" required>
        </div>


        <div class="form-group">
    <label for="customer_phone">Phone Number:</label>
    <input type="tel" name="customer_phone" id="customer_phone" 
           placeholder="+255712345678" 
           pattern="^\+255[0-9]{9}$" 
           title="Phone number must start with +255 followed by 9 digits (e.g., +255712345678)" required>
           </div>

       
        
        <h3>Products</h3>
        <div id="product-container">
            <!-- Product rows will be added here -->
        </div>
        
        <button type="button" id="add-product" class="add-btn">+ Add Product</button>
        
        <div id="summary">
            <div class="form-group">
                <label>Total Amount:</label>
                <span id="total-amount">0.00</span>
            </div>
            
            <div class="form-group">
                <label for="amount_paid">Amount Paid:</label>
                <input type="number" name="amount_paid" id="amount_paid" min="0" step="0.01" required>
                <span id="balance-warning" class="balance-warning"></span>
            </div>
            
            <div class="form-group">
                <label>Balance:</label>
                <span id="balance-amount">0.00</span>
            </div>
        </div>
        
        <button type="submit" class="submit-btn">Record Sale</button>
    </form>

    <script>
        // Product data from PHP
        const products = <?= json_encode($products) ?>;
        let productCounter = 0;

        // Add new product row
        function addProductRow(selectedProductId = '', quantity = 1) {
            const container = document.getElementById('product-container');
            const rowId = `product-${productCounter++}`;
            
            const row = document.createElement('div');
            row.className = 'product-row';
            row.id = rowId;
            
            // Product select dropdown
            const selectDiv = document.createElement('div');
            selectDiv.className = 'product-select';
            
            const select = document.createElement('select');
            select.name = `products[${rowId}][id]`;
            select.required = true;
            select.addEventListener('change', updateProductSelection);
            
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = '-- Select Product --';
            select.appendChild(defaultOption);
            
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = `${product.item_name} ${product.size ? '('+product.size+')' : ''}`;
                option.dataset.price = product.price;
                option.dataset.stock = product.quantity;
                if (product.id == selectedProductId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            
            selectDiv.appendChild(select);
            row.appendChild(selectDiv);
            
            // Quantity input
            const qtyDiv = document.createElement('div');
            qtyDiv.className = 'quantity-input';
            
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.name = `products[${rowId}][quantity]`;
            qtyInput.min = 1;
            qtyInput.value = quantity;
            qtyInput.required = true;
            qtyInput.addEventListener('input', updateTotalAndStock);
            
            qtyDiv.appendChild(qtyInput);
            row.appendChild(qtyDiv);
            
            // Stock information
            const stockDiv = document.createElement('div');
            stockDiv.className = 'stock-info';
            stockDiv.innerHTML = '<span class="stock-label">Stock:</span> <span class="stock-value">-</span>';
            row.appendChild(stockDiv);
            
            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-btn';
            removeBtn.textContent = '×';
            removeBtn.addEventListener('click', () => {
                container.removeChild(row);
                updateTotal();
            });
            
            row.appendChild(removeBtn);
            container.appendChild(row);
            
            // Trigger change event to set initial values if product is selected
            if (selectedProductId) {
                select.dispatchEvent(new Event('change'));
                qtyInput.value = quantity;
                qtyInput.dispatchEvent(new Event('input'));
            }
        }
        
        // Update product selection and stock info
        function updateProductSelection(event) {
            const select = event.target;
            const row = select.closest('.product-row');
            const stockValue = row.querySelector('.stock-value');
            const quantityInput = row.querySelector('input[type="number"]');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const availableStock = parseInt(selectedOption.dataset.stock);
                
                stockValue.textContent = availableStock;
                quantityInput.max = availableStock;
                
                if (parseInt(quantityInput.value) > availableStock) {
                    quantityInput.value = availableStock;
                }
            } else {
                stockValue.textContent = '-';
                quantityInput.removeAttribute('max');
            }
            
            updateTotal();
        }
        
        // Update stock display when quantity changes
        function updateTotalAndStock(event) {
            const quantityInput = event.target;
            const row = quantityInput.closest('.product-row');
            const select = row.querySelector('select');
            const stockValue = row.querySelector('.stock-value');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const availableStock = parseInt(selectedOption.dataset.stock);
                const requestedQty = parseInt(quantityInput.value) || 0;
                
                if (requestedQty > availableStock) {
                    quantityInput.value = availableStock;
                }
                
                stockValue.textContent = availableStock - (parseInt(quantityInput.value) || 0);
            }
            
            updateTotal();
        }
        
        // Calculate and update total amount and balance
        function updateTotal() {
            let total = 0;
            
            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('select');
                const quantityInput = row.querySelector('input[type="number"]');
                
                if (select.value && quantityInput.value) {
                    const price = parseFloat(select.options[select.selectedIndex].dataset.price);
                    const quantity = parseInt(quantityInput.value);
                    total += price * quantity;
                }
            });
            
            const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
            const balance = total - amountPaid;
            
            document.getElementById('total-amount').textContent = total.toFixed(2);
            document.getElementById('balance-amount').textContent = balance.toFixed(2);
            
            // Show warning if balance exists
            const balanceWarning = document.getElementById('balance-warning');
            if (balance > 0) {
                balanceWarning.style.display = 'inline';
                balanceWarning.textContent = `Customer will owe: ${balance.toFixed(2)}`;
            } else {
                balanceWarning.style.display = 'none';
            }
        }
        
        // Add amount paid listener
        document.getElementById('amount_paid').addEventListener('input', updateTotal);
        
        // Add initial product row
        document.getElementById('add-product').addEventListener('click', () => addProductRow());
        
        // Add one product row by default when page loads
        document.addEventListener('DOMContentLoaded', () => addProductRow());


        
    </script>
</body>
</html>