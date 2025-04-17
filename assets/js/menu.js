/**
 * menu.js - Client-side functionality for digital menu system
 */

let cart = {};
let tableId = 0;

/**
 * Initialize the menu functionality
 * @param {number} id - Table ID
 */
function initMenu(id) {
    tableId = id;
    
    // Set up quantity buttons
    setupQuantityButtons();
    
    // Set up cart functionality
    setupCartFunctionality();
    
    // Set up waiter call modal
    setupWaiterModal();
    
    // Set up order tracking (if applicable)
    checkOrderStatus();
    
    // Listen for scroll to highlight active category
    window.addEventListener('scroll', highlightActiveCategory);
}

/**
 * Set up the plus/minus quantity buttons
 */
function setupQuantityButtons() {
    // Plus buttons
    document.querySelectorAll('.qty-plus').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemId = this.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${itemId}"]`);
            let value = parseInt(input.value);
            if (value < parseInt(input.max)) {
                input.value = value + 1;
                updateCart(itemId, value + 1);
            }
        });
    });
    
    // Minus buttons
    document.querySelectorAll('.qty-minus').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemId = this.dataset.id;
            const input = document.querySelector(`.qty-input[data-id="${itemId}"]`);
            let value = parseInt(input.value);
            if (value > 0) {
                input.value = value - 1;
                updateCart(itemId, value - 1);
            }
        });
    });
    
    // Direct input changes
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const itemId = this.dataset.id;
            let value = parseInt(this.value);
            
            // Enforce min/max
            if (isNaN(value) || value < 0) {
                value = 0;
            } else if (value > parseInt(this.max)) {
                value = parseInt(this.max);
            }
            
            this.value = value;
            updateCart(itemId, value);
        });
    });
}

/**
 * Update cart with new quantity
 * @param {string} itemId - Menu item ID
 * @param {number} quantity - New quantity
 */
function updateCart(itemId, quantity) {
    // Get item details
    const itemElement = document.querySelector(`.menu-item[data-id="${itemId}"]`);
    const itemName = itemElement.querySelector('.item-name').textContent;
    
    let itemPrice = 0;
    const specialPrice = itemElement.querySelector('.special-price');
    if (specialPrice) {
        itemPrice = parseFloat(specialPrice.textContent.replace(/[^0-9.-]+/g, ''));
    } else {
        itemPrice = parseFloat(itemElement.querySelector('.item-price').textContent.replace(/[^0-9.-]+/g, ''));
    }
    
    // Update or remove from cart
    if (quantity > 0) {
        cart[itemId] = {
            name: itemName,
            price: itemPrice,
            quantity: quantity,
            instructions: document.querySelector(`textarea[name="instructions[${itemId}]"]`).value
        };
    } else if (cart[itemId]) {
        delete cart[itemId];
    }
    
    // Update UI
    updateCartUI();
}

/**
 * Update the cart UI with current items
 */
function updateCartUI() {
    const cartCount = document.querySelector('.cart-count');
    const orderItemsList = document.getElementById('order-items-list');
    const placeOrderBtn = document.getElementById('place-order-btn');
    let totalAmount = 0;
    let totalItems = 0;
    
    // Clear existing items
    orderItemsList.innerHTML = '';
    
    // If cart is empty
    if (Object.keys(cart).length === 0) {
        orderItemsList.innerHTML = '<p class="empty-cart">Your cart is empty. Add items to place an order.</p>';
        placeOrderBtn.disabled = true;
    } else {
        // Add items to cart display
        for (const [itemId, item] of Object.entries(cart)) {
            totalItems += item.quantity;
            const itemTotal = item.price * item.quantity;
            totalAmount += itemTotal;
            
            const itemElement = document.createElement('div');
            itemElement.className = 'order-item';
            itemElement.innerHTML = `
                <div class="order-item-details">
                    <span class="order-item-name">${item.name}</span>
                    <span class="order-item-price">$${item.price.toFixed(2)} × ${item.quantity}</span>
                </div>
                <div class="order-item-total">$${itemTotal.toFixed(2)}</div>
            `;
            
            if (item.instructions) {
                const instructionsElement = document.createElement('div');
                instructionsElement.className = 'order-item-instructions';
                instructionsElement.textContent = item.instructions;
                itemElement.appendChild(instructionsElement);
            }
            
            orderItemsList.appendChild(itemElement);
        }
        
        placeOrderBtn.disabled = false;
    }
    
    // Update total and cart count
    document.getElementById('total-amount').textContent = `$${totalAmount.toFixed(2)}`;
    cartCount.textContent = totalItems;
    
    // Highlight cart icon if not empty
    if (totalItems > 0) {
        document.getElementById('cart-icon').classList.add('has-items');
    } else {
        document.getElementById('cart-icon').classList.remove('has-items');
    }
}

/**
 * Set up cart-related functionality
 */
function setupCartFunctionality() {
    // Cart icon click to scroll to summary
    document.getElementById('cart-icon').addEventListener('click', function() {
        document.getElementById('order-summary').scrollIntoView({ behavior: 'smooth' });
    });
    
    // Clear cart button
    document.getElementById('clear-cart-btn').addEventListener('click', function(e) {
        e.preventDefault();
        
        // Reset all quantities to 0
        document.querySelectorAll('.qty-input').forEach(input => {
            input.value = 0;
        });
        
        // Clear instructions
        document.querySelectorAll('textarea[name^="instructions"]').forEach(textarea => {
            textarea.value = '';
        });
        
        // Clear cart object and update UI
        cart = {};
        updateCartUI();
    });
}

/**
 * Set up waiter call modal
 */
function setupWaiterModal() {
    const modal = document.getElementById('waiter-modal');
    const btn = document.getElementById('call-waiter-btn');
    const closeBtn = document.querySelector('.close-modal');
    const waiterForm = document.getElementById('waiter-form');
    const waiterResponse = document.getElementById('waiter-response');
    const requestButtons = document.querySelectorAll('.request-btn');
    
    // Open modal
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        modal.style.display = 'block';
        waiterResponse.classList.add('hidden');
        waiterForm.reset();
    });
    
    // Close modal
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Handle pre-defined request buttons
    requestButtons.forEach(button => {
        button.addEventListener('click', function() {
            const requestType = this.dataset.request;
            document.getElementById('other-request').value = `Please bring ${requestType}`;
            
            // Highlight active button
            requestButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Submit request
    waiterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Prepare request data
        const requestText = document.getElementById('other-request').value.trim();
        if (!requestText) {
            alert('Please specify your request');
            return;
        }
        
        // Send request to server (via AJAX)
        const requestData = {
            table_id: tableId,
            request: requestText
        };
        
        // Show loading state
        this.querySelector('button[type="submit"]').disabled = true;
        this.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        // Send the request (can be replaced with actual AJAX)
        setTimeout(() => {
            // Show success message
            waiterForm.classList.add('hidden');
            waiterResponse.classList.remove('hidden');
            
            // Reset form for next use
            this.querySelector('button[type="submit"]').disabled = false;
            this.querySelector('button[type="submit"]').innerHTML = 'Send Request';
            this.reset();
            
            // Close modal after delay
            setTimeout(() => {
                modal.style.display = 'none';
                waiterForm.classList.remove('hidden');
                waiterResponse.classList.add('hidden');
            }, 3000);
        }, 1000);
    });
}

/**
 * Check if there's an active order to track
 */
function checkOrderStatus() {
    const params = new URLSearchParams(window.location.search);
    const orderNumber = params.get('order');
    
    if (orderNumber) {
        // TODO: Implement order status tracking
        console.log('Tracking order:', orderNumber);
    }
}

/**
 * Highlight active category based on scroll position
 */
function highlightActiveCategory() {
    const sections = document.querySelectorAll('.menu-section');
    const navItems = document.querySelectorAll('.category-nav a');
    
    let currentSection = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        
        if (window.pageYOffset >= sectionTop - 200 && 
            window.pageYOffset < sectionTop + sectionHeight - 200) {
            currentSection = section.getAttribute('id');
        }
    });
    
    navItems.forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('href') === '#' + currentSection) {
            item.classList.add('active');
        }
    });
}