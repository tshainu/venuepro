// VenuePro Lanka - App JS

// Auto-dismiss alerts after 4s
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (el) {
            var alert = bootstrap.Alert.getOrCreateInstance(el);
            if (alert) alert.close();
        });
    }, 4000);

    // Confirm delete
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var msg = btn.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // Auto-calculate totals in booking form
    initBookingCalc();
});

function initBookingCalc() {
    var rows = document.querySelectorAll('.addon-row');
    rows.forEach(function (row) {
        var qty = row.querySelector('.addon-qty');
        var price = row.querySelector('.addon-price');
        var total = row.querySelector('.addon-total');
        if (qty && price && total) {
            [qty, price].forEach(function (el) {
                el.addEventListener('input', function () {
                    total.textContent = 'Rs. ' + (parseFloat(qty.value || 0) * parseFloat(price.value || 0)).toFixed(2);
                    updateGrandTotal();
                });
            });
        }
    });
}

function updateGrandTotal() {
    var subtotal = 0;
    document.querySelectorAll('.addon-total[data-amount]').forEach(function (el) {
        subtotal += parseFloat(el.dataset.amount || 0);
    });
    var discountEl = document.getElementById('discount_amount');
    var taxEl = document.getElementById('tax_percent');
    var discount = discountEl ? parseFloat(discountEl.value || 0) : 0;
    var taxPct = taxEl ? parseFloat(taxEl.value || 0) : 0;
    var tax = (subtotal - discount) * taxPct / 100;
    var total = subtotal - discount + tax;
    if (document.getElementById('summary_subtotal')) document.getElementById('summary_subtotal').textContent = 'Rs. ' + subtotal.toFixed(2);
    if (document.getElementById('summary_discount')) document.getElementById('summary_discount').textContent = 'Rs. ' + discount.toFixed(2);
    if (document.getElementById('summary_tax')) document.getElementById('summary_tax').textContent = 'Rs. ' + tax.toFixed(2);
    if (document.getElementById('summary_total')) document.getElementById('summary_total').textContent = 'Rs. ' + total.toFixed(2);
}

// Format currency input
function formatCurrency(val) {
    return 'Rs. ' + parseFloat(val || 0).toLocaleString('en-LK', { minimumFractionDigits: 2 });
}
