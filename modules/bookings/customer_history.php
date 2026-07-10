<?php
require_once __DIR__ . "/../../core/bootstrap.php";
Auth::check();
$db = Database::getInstance();
$cid = (int)($_GET["customer_id"] ?? 0);
if (!$cid) { echo "<div class=\"alert alert-warning\">Invalid customer ID</div>"; exit; }

$history = $db->fetchAll(
    "SELECT b.*, h.name as hall_name 
     FROM bookings b 
     LEFT JOIN halls h ON b.hall_id = h.id 
     WHERE b.customer_id = ? 
     ORDER BY b.event_date DESC", 
    [$cid]
);

if (!$history) {
    echo "<div class=\"text-center py-4\"><p class=\"text-muted mb-0\">No previous bookings found for this customer.</p></div>";
    exit;
}
?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.85rem;">
        <thead class="bg-light">
            <tr>
                <th>Ref</th>
                <th>Event Date</th>
                <th>Event Type</th>
                <th>Hall</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $b): 
                $statusColor = match($b["status"]) {
                    "confirmed", "booked" => "success",
                    "completed" => "primary",
                    "cancelled" => "danger",
                    "tentative" => "warning",
                    default => "secondary"
                };
            ?>
            <tr>
                <td class="fw-bold text-navy"><?= $b["booking_ref"] ?></td>
                <td><?= Helper::formatDate($b["event_date"]) ?></td>
                <td><?= $b["event_type"] ?></td>
                <td><?= $b["hall_name"] ?? "N/A" ?></td>
                <td class="fw-bold"><?= Helper::formatCurrency($b["final_amount"]) ?></td>
                <td><span class="badge bg-<?= $statusColor ?>-subtle text-<?= $statusColor ?> border border-<?= $statusColor ?>-subtle px-2"><?= ucfirst($b["status"]) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
