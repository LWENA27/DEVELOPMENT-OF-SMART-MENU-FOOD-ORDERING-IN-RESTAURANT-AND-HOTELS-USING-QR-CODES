// src/scripts/admin.js
document.addEventListener('DOMContentLoaded', function() {
    const menuForm = document.getElementById('menu-form');
    const orderForm = document.getElementById('order-form');

    if (menuForm) {
        menuForm.addEventListener('submit', function(event) {
            event.preventDefault();
            // Perform form validation and submission logic for menu management
            validateMenuForm();
        });
    }

    if (orderForm) {
        orderForm.addEventListener('submit', function(event) {
            event.preventDefault();
            // Perform form validation and submission logic for order management
            validateOrderForm();
        });
    }

    function validateMenuForm() {
        const itemName = document.getElementById('item-name').value;
        const itemPrice = document.getElementById('item-price').value;

        if (!itemName || !itemPrice) {
            alert('Please fill in all fields.');
            return false;
        }

        // Additional validation logic can be added here

        menuForm.submit();
    }

    function validateOrderForm() {
        const orderId = document.getElementById('order-id').value;

        if (!orderId) {
            alert('Please enter a valid order ID.');
            return false;
        }

        // Additional validation logic can be added here

        orderForm.submit();
    }
});