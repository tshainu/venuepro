<?php
/**
 * Helper function to classify event as Day or Night based on event time
 * Day Event: Before 6 PM (18:00)
 * Night Event: 6 PM (18:00) and after
 */

function getEventTimeType($eventTime) {
    if (!$eventTime) {
        return null;
    }
    
    // Convert time string to hour (24-hour format)
    $time = strtotime($eventTime);
    $hour = (int)date('H', $time);
    
    // 18 = 6 PM
    if ($hour < 18) {
        return 'Day Event';
    } else {
        return 'Night Event';
    }
}

function getEventTimeTypeClass($eventTime) {
    $type = getEventTimeType($eventTime);
    
    if ($type === 'Day Event') {
        return 'badge-info'; // Blue for day
    } elseif ($type === 'Night Event') {
        return 'badge-dark'; // Dark for night
    }
    
    return 'badge-secondary';
}

function getEventTimeTypeColor($eventTime) {
    $type = getEventTimeType($eventTime);
    
    if ($type === 'Day Event') {
        return '#3498db'; // Blue
    } elseif ($type === 'Night Event') {
        return '#2c3e50'; // Dark
    }
    
    return '#95a5a6'; // Gray
}
?>
