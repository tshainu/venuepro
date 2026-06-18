# VenuePro Build Progress

## Done
- [x] packages/delete.php
- [x] addons/ (index, create, edit, delete)
- [x] bookings/ (index, create, view, edit, delete)

## In Progress
- [ ] quotations/ (index, create, view, pdf)
- [ ] invoices/ (index, create, view, pdf)
- [ ] payments/ (index, create, view)
- [ ] reports/ (index, revenue, bookings, payments)
- [ ] users/ (index, create, edit, delete)
- [ ] branches/ (index, create, edit, delete)
- [ ] settings/ (index)
- [ ] .htaccess
- [ ] Smoke test + zip

## Key Facts
- DB: venuepro / VenuePro@2026 / sudo mysql -u root
- BASE_URL: http://localhost:8080 (set in config)
- mPDF at /vendor/autoload.php
- tmp/mpdf/ for pdf temp
- Helper::generateRef('QT', 'quotations', 'quotation_ref')
- Helper::generateRef('INV', 'invoices', 'invoice_number')
- Helper::generateRef('PAY', 'payments', 'payment_ref')
