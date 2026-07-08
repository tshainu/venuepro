<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

// Fetch booking details
$booking = $db->fetchOne(
    "SELECT b.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            h.name as hall_name, h.capacity as hall_capacity,
            p.name as package_name, p.price as package_price,
            br.name as branch_name, br.address as branch_address, br.phone as branch_phone, br.email as branch_email,
            u.name as created_by_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     LEFT JOIN packages p ON b.package_id=p.id
     LEFT JOIN branches br ON b.branch_id=br.id
     LEFT JOIN users u ON b.created_by=u.id
     WHERE b.id=?", [$id]
);

if (!$booking) die('Booking not found.');
if ($cu['branch_id'] && $booking['branch_id'] != $cu['branch_id']) die('Access denied.');

// Fetch add-ons for this booking
$addons = $db->fetchAll("SELECT * FROM booking_addons WHERE booking_id=?", [$id]);

// Company settings
$app_name      = Helper::getSetting('company_name', $booking['branch_id']) ?? Helper::getSetting('company_name') ?? APP_NAME;
$co_tagline    = Helper::getSetting('company_tagline', $booking['branch_id']) ?? '';
$co_address    = Helper::getSetting('company_address', $booking['branch_id']) ?? $booking['branch_address'] ?? '';
$co_phone      = Helper::getSetting('company_phone', $booking['branch_id']) ?? $booking['branch_phone'] ?? '';
$co_email      = Helper::getSetting('company_email', $booking['branch_id']) ?? $booking['branch_email'] ?? '';
$cur           = Helper::getSetting('currency_symbol', $booking['branch_id']) ?? 'Rs.';

// Branding
$logo          = Helper::getSetting('company_logo', $booking['branch_id']);
$show_logo     = Helper::getSetting('booking_show_logo', $booking['branch_id']) ?? '1';
$logo_path     = ($show_logo === '1' && $logo && file_exists(ROOT_PATH.'/uploads/'.$logo)) ? ROOT_PATH.'/uploads/'.$logo : null;

// Helper function
function money($v, $cur) { return $cur.' '.number_format((float)$v, 2); }

// Event time classification function
function getEventTimeType($eventTime) {
    if (!$eventTime) return null;
    $time = strtotime($eventTime);
    $hour = (int)date('H', $time);
    return $hour < 18 ? 'Day Event' : 'Night Event';
}

function getEventTimeColor($eventTime) {
    $type = getEventTimeType($eventTime);
    return $type === 'Day Event' ? '#3498db' : '#2c3e50';
}

// Format dates
$booking_date = date('d M Y', strtotime($booking['created_at'] ?? 'now'));
$event_date_formatted = date('d M Y', strtotime($booking['event_date']));
$event_time_formatted = $booking['event_time'] ? date('h:i A', strtotime('2000-01-01 '.$booking['event_time'])) : 'Not specified';
$event_time_type = getEventTimeType($booking['event_time']);
$event_time_color = getEventTimeColor($booking['event_time']);

// Determine event person
$event_person = '';
$event_person_label = '';

if ($booking['event_type'] == 'Wedding' || $booking['event_type'] == 'Engagement' || $booking['event_type'] == 'Wedding Reception') {
    if ($booking['bride_name'] && $booking['groom_name']) {
        $event_person = htmlspecialchars($booking['bride_name']) . ' & ' . htmlspecialchars($booking['groom_name']);
        $event_person_label = 'Bride & Groom';
    } elseif ($booking['bride_name']) {
        $event_person = htmlspecialchars($booking['bride_name']);
        $event_person_label = 'Bride';
    } elseif ($booking['groom_name']) {
        $event_person = htmlspecialchars($booking['groom_name']);
        $event_person_label = 'Groom';
    }
} elseif ($booking['event_type'] == 'Birthday') {
    if ($booking['hero_name']) {
        $event_person = htmlspecialchars($booking['hero_name']);
        $event_person_label = 'Birthday Person';
    }
} elseif ($booking['hero_name']) {
    $event_person = htmlspecialchars($booking['hero_name']);
    $event_person_label = 'Guest of Honor';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Sheet - <?= htmlspecialchars($booking['booking_ref']) ?></title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  
  body {
    font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    background: #f5f5f5;
    padding: 20px;
    color: #333;
  }

  .print-container {
    max-width: 850px;
    margin: 0 auto;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  }

  /* ===== BUTTON AREA ===== */
  .button-area {
    text-align: center;
    padding: 15px;
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .btn {
    color: white;
    border: none;
    padding: 10px 25px;
    font-size: 10pt;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-print {
    background: #c9a84c;
  }

  .btn-print:hover {
    background: #a07820;
  }

  .btn-download {
    background: #059669;
  }

  .btn-download:hover {
    background: #047857;
  }

  /* ===== HEADER ===== */
  .header {
    background: linear-gradient(135deg, #fef9f3 0%, #fffcf7 100%);
    padding: 25px;
    border-bottom: 3px solid #c9a84c;
    display: flex;
    gap: 20px;
    align-items: flex-start;
  }

  .logo-box {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .logo {
    max-height: 90px;
    max-width: 140px;
    object-fit: contain;
    display: block;
  }

  .company-header {
    flex: 1;
  }

  .company-name {
    font-size: 16pt;
    font-weight: bold;
    color: #c9a84c;
    margin-bottom: 3px;
  }

  .company-tagline {
    font-size: 8pt;
    color: #999;
    margin-bottom: 5px;
  }

  .company-details {
    font-size: 8pt;
    color: #666;
    line-height: 1.4;
  }

  .header-right {
    flex: 0 0 auto;
    text-align: right;
  }

  .booking-title {
    font-size: 20pt;
    font-weight: bold;
    color: #c9a84c;
    letter-spacing: 1px;
    margin-bottom: 8px;
  }

  .booking-ref {
    background: #c9a84c;
    color: white;
    padding: 6px 12px;
    border-radius: 3px;
    font-size: 9pt;
    font-weight: bold;
    display: inline-block;
  }

  /* ===== EVENT PERSON ===== */
  .event-person {
    background: linear-gradient(135deg, #fef5e0 0%, #fffbf0 100%);
    border-top: 2px solid #c9a84c;
    border-bottom: 2px solid #c9a84c;
    padding: 18px;
    text-align: center;
  }

  .event-person-label {
    font-size: 8pt;
    font-weight: bold;
    color: #c9a84c;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
  }

  .event-person-name {
    font-size: 14pt;
    font-weight: bold;
    color: #333;
  }

  /* ===== CONTENT ===== */
  .content {
    padding: 20px;
  }

  /* ===== INFO BOXES ===== */
  .info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
  }

  .info-box {
    background: #f9f9f9;
    padding: 12px;
    border-left: 3px solid #c9a84c;
  }

  .info-title {
    font-size: 8pt;
    font-weight: bold;
    color: #c9a84c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #ddd;
  }

  .info-item {
    display: flex;
    justify-content: space-between;
    font-size: 8pt;
    margin-bottom: 5px;
  }

  .info-item:last-child {
    margin-bottom: 0;
  }

  .info-label {
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 7pt;
  }

  .info-value {
    color: #333;
    font-weight: 600;
    text-align: right;
  }

  /* ===== TABLE ===== */
  .section-title {
    font-size: 9pt;
    font-weight: bold;
    color: #c9a84c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 15px 0 10px 0;
    padding-bottom: 6px;
    border-bottom: 2px solid #c9a84c;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
  }

  table thead {
    background: #c9a84c;
    color: white;
  }

  table thead th {
    padding: 10px;
    text-align: left;
    font-size: 8pt;
    font-weight: bold;
    text-transform: uppercase;
  }

  table thead th.right {
    text-align: right;
  }

  table tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid #eee;
    font-size: 8pt;
  }

  table tbody td.right {
    text-align: right;
    font-weight: 600;
  }

  table tbody tr:nth-child(even) {
    background: #fafafa;
  }

  /* ===== TOTALS ===== */
  .totals {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 15px;
  }

  .totals-box {
    width: 45%;
  }

  .total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
    font-size: 8pt;
  }

  .total-row.grand {
    background: #c9a84c;
    color: white;
    font-weight: bold;
    padding: 10px 12px;
    border: none;
  }

  .total-row.balance {
    background: #f0f0f0;
    color: #c9a84c;
    font-weight: bold;
    padding: 8px 12px;
  }

  /* ===== SIGNATURE ===== */
  .signature-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 2px solid #c9a84c;
  }

  .signature-title {
    font-size: 8pt;
    font-weight: bold;
    color: #c9a84c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 15px;
  }

  .signature-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
  }

  .signature-box {
    text-align: center;
  }

  .signature-area {
    border: 1px solid #999;
    height: 45px;
    margin-bottom: 8px;
    background: #fafafa;
  }

  .signature-label {
    font-size: 7pt;
    font-weight: bold;
    color: #c9a84c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }

  .signature-name {
    font-size: 8pt;
    font-weight: 600;
    color: #333;
    padding-top: 4px;
    border-top: 1px solid #999;
  }

  /* ===== FOOTER ===== */
  .footer {
    background: #f9f9f9;
    padding: 12px;
    text-align: center;
    font-size: 7pt;
    color: #999;
    border-top: 1px solid #ddd;
  }

  @media print {
    body { background: white; padding: 0; }
    .print-container { box-shadow: none; margin: 0; }
    .button-area { display: none; }
  }
</style>
</head>
<body>

<div class="print-container">

  <!-- BUTTONS -->
  <div class="button-area no-print">
    <button class="btn btn-print" onclick="window.print()">🖨️ Print</button>
    <button class="btn btn-download" onclick="downloadPDF()">⬇️ Download PDF</button>
  </div>

  <script>
    function downloadPDF() {
      const element = document.querySelector('.print-container');
      if (typeof html2pdf !== 'undefined') {
        html2pdf().set({
          margin: 0,
          filename: 'Booking_<?= htmlspecialchars($booking['booking_ref']) ?>.pdf',
          image: { type: 'jpeg', quality: 0.98 },
          html2canvas: { scale: 2 },
          jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
        }).from(element).save();
      } else {
        alert('Select "Save as PDF" from printer options');
        window.print();
      }
    }
  </script>

  <!-- HEADER -->
  <div class="header">
    <div class="logo-box">
      <?php if ($logo_path): ?>
        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($logo) ?>" alt="Logo" class="logo">
      <?php endif; ?>
    </div>
    <div class="company-header">
      <div class="company-name"><?= htmlspecialchars($app_name) ?></div>
      <?php if ($co_tagline): ?><div class="company-tagline"><?= htmlspecialchars($co_tagline) ?></div><?php endif; ?>
      <div class="company-details">
        <?php if ($co_address): ?><?= htmlspecialchars($co_address) ?><br><?php endif; ?>
        <?php if ($co_phone): ?>Tel: <?= htmlspecialchars($co_phone) ?><?php endif; ?>
        <?php if ($co_email): ?> | <?= htmlspecialchars($co_email) ?><?php endif; ?>
      </div>
    </div>
    <div class="header-right">
      <div class="booking-title">BOOKING SHEET</div>
      <div class="booking-ref"><?= htmlspecialchars($booking['booking_ref']) ?></div>
    </div>
  </div>

  <!-- EVENT PERSON -->
  <?php if ($event_person): ?>
  <div class="event-person">
    <div class="event-person-label"><?= $event_person_label ?></div>
    <div class="event-person-name"><?= $event_person ?></div>
  </div>
  <?php endif; ?>

  <!-- CONTENT -->
  <div class="content">

    <!-- BOOKING & EVENT INFO -->
    <div class="info-row">
      <div class="info-box">
        <div class="info-title">Booking Information</div>
        <div class="info-item">
          <span class="info-label">Booking Date</span>
          <span class="info-value"><?= $booking_date ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Booking Ref</span>
          <span class="info-value"><?= htmlspecialchars($booking['booking_ref']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Status</span>
          <span class="info-value" style="color: #c9a84c; font-weight: bold;"><?= htmlspecialchars($booking['status']) ?></span>
        </div>
      </div>
      <div class="info-box">
        <div class="info-title">Event Information</div>
        <div class="info-item">
          <span class="info-label">Event Date</span>
          <span class="info-value"><?= $event_date_formatted ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Event Time</span>
          <span class="info-value"><?= $event_time_formatted ?></span>
        </div>
        <?php if ($event_time_type): ?>
        <div class="info-item">
          <span class="info-label">Event Type</span>
          <span class="info-value" style="background: <?= $event_time_color ?>; color: white; padding: 2px 8px; border-radius: 3px; display: inline-block;"><?= $event_time_type ?></span>
        </div>
        <?php endif; ?>
        <div class="info-item">
          <span class="info-label">Guest Count</span>
          <span class="info-value"><?= number_format((int)$booking['guest_count']) ?></span>
        </div>
      </div>
    </div>

    <!-- CUSTOMER & EVENT DETAILS -->
    <div class="info-row">
      <div class="info-box">
        <div class="info-title">Customer</div>
        <div style="font-size: 9pt; font-weight: bold; margin-bottom: 8px; color: #333;"><?= htmlspecialchars($booking['customer_name']) ?></div>
        <div class="info-item">
          <span class="info-label">Mobile</span>
          <span class="info-value"><?= htmlspecialchars($booking['customer_phone'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Email</span>
          <span class="info-value"><?= htmlspecialchars($booking['customer_email'] ?? 'N/A') ?></span>
        </div>
      </div>
      <div class="info-box">
        <div class="info-title">Event Details</div>
        <div style="font-size: 9pt; font-weight: bold; margin-bottom: 8px; color: #333;"><?= htmlspecialchars($booking['event_type']) ?></div>
        <div class="info-item">
          <span class="info-label">Hall</span>
          <span class="info-value"><?= htmlspecialchars($booking['hall_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Capacity</span>
          <span class="info-value"><?= (int)$booking['hall_capacity'] ?? 'N/A' ?> seats</span>
        </div>
      </div>
    </div>

    <!-- ITEMS TABLE -->
    <div class="section-title">Services & Add-ons</div>
    <table>
      <thead>
        <tr>
          <th>Description</th>
          <th class="right">Price</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($booking['hall_name']): ?>
        <tr>
          <td><?= htmlspecialchars($booking['hall_name']) ?> (<?= (int)$booking['hall_capacity'] ?> guests)</td>
          <td class="right"><?= money($booking['total_amount'], $cur) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($booking['package_name']): ?>
        <tr>
          <td>Package: <?= htmlspecialchars($booking['package_name']) ?></td>
          <td class="right">Included</td>
        </tr>
        <?php endif; ?>
        <?php foreach ($addons as $addon): ?>
        <tr>
          <td><?= htmlspecialchars($addon['name']) ?> (<?= (int)$addon['quantity'] ?> <?= htmlspecialchars($addon['unit'] ?? '') ?>)</td>
          <td class="right"><?= money($addon['total_price'], $cur) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- TOTALS -->
    <div class="totals">
      <div class="totals-box">
        <div class="total-row">
          <span>Subtotal:</span>
          <span><?= money($booking['total_amount'], $cur) ?></span>
        </div>
        <?php if ((float)$booking['discount_amount'] > 0): ?>
        <div class="total-row">
          <span>Discount:</span>
          <span>-<?= money($booking['discount_amount'], $cur) ?></span>
        </div>
        <?php endif; ?>
        <?php if ((float)$booking['tax_amount'] > 0): ?>
        <div class="total-row">
          <span>Tax:</span>
          <span><?= money($booking['tax_amount'], $cur) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row grand">
          <span>TOTAL AMOUNT:</span>
          <span><?= money($booking['final_amount'], $cur) ?></span>
        </div>
        <?php if ((float)$booking['balance_amount'] > 0): ?>
        <div class="total-row balance">
          <span>Balance Due:</span>
          <span><?= money($booking['balance_amount'], $cur) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SIGNATURE -->
    <div class="signature-section">
      <div class="signature-title">Acknowledgement & Authorization</div>
      <div class="signature-grid">
        <div class="signature-box">
          <div class="signature-area"></div>
          <div class="signature-label">Customer Signature</div>
          <div class="signature-name"><?= htmlspecialchars($booking['customer_name']) ?></div>
        </div>
        <div class="signature-box">
          <div class="signature-area"></div>
          <div class="signature-label">Manager/Authorized Person</div>
          <div class="signature-name"><?= htmlspecialchars($booking['created_by_name'] ?? 'Booking Manager') ?></div>
        </div>
      </div>
    </div>

  </div>

  <!-- FOOTER -->
  <div class="footer">
    Official booking confirmation from <?= htmlspecialchars($app_name) ?>. Generated on <?= date('d M Y, h:i A') ?>
  </div>

</div>

</body>
</html>
