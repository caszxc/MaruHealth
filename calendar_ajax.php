<?php
//calendar_ajax.php
include 'config.php';

$month = intval($_GET['month']);
$year = intval($_GET['year']);

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$dayOfWeek = date('w', $firstDayOfMonth);
$monthName = date('F', $firstDayOfMonth);
$today = date('Y-n-j');

// Fetch current month events
$startDate = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-01";
$endDate = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-$daysInMonth";

$stmt = $conn->prepare("SELECT title, event_date FROM events WHERE event_date BETWEEN :start AND :end");
$stmt->execute(['start' => $startDate, 'end' => $endDate]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$eventsByDate = [];
foreach ($events as $event) {
    $eventsByDate[$event['event_date']][] = $event['title'];
}

echo '<div class="month-navigation">';
echo '<button class="nav-btn" onclick="changeMonth(-1)">&#8249;</button>';
echo "<h2>$monthName $year</h2>";
echo '<button class="nav-btn" onclick="changeMonth(1)">&#8250;</button>';
echo '</div>';

// Header table for days of the week
echo '<table class="calendar-header">';
echo '<tr>';
$daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
foreach ($daysOfWeek as $day) {
    echo "<th>$day</th>";
}
echo '</tr>';
echo '</table>';

// Body table for calendar days
echo '<table class="calendar-body">';
echo '<tr>';

// Previous month details
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$daysInPrevMonth = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
$prevMonthStart = $daysInPrevMonth - $dayOfWeek + 1;

// Fill in days from previous month (only if needed)
for ($i = 0; $i < $dayOfWeek; $i++) {
    $dayNum = $prevMonthStart + $i;
    echo "<td class='faded-day'>$dayNum</td>";
}

// Main month days
$cellsInCurrentRow = $dayOfWeek; // number of cells already filled in first row
for ($day = 1; $day <= $daysInMonth; $day++) {
    // Start new row if needed
    if ($cellsInCurrentRow >= 7) {
        echo '</tr><tr>';
        $cellsInCurrentRow = 0;
    }

    $date = "$year-" . str_pad($month, 2, "0", STR_PAD_LEFT) . "-" . str_pad($day, 2, "0", STR_PAD_LEFT);
    $isToday = ($date == $today) ? 'selected-day' : '';
    $dayId = "day-$year-$month-$day";

    echo "<td class='$isToday' data-date='$date' onclick=\"showEvents($year, $month, $day)\">
            <div class='day-container' id='$dayId'>
                <div class='day-number'>$day</div>";

    if (isset($eventsByDate[$date])) {
        foreach ($eventsByDate[$date] as $title) {
            echo "<div class='event-title'>" . htmlspecialchars($title) . "</div>";
        }
    }

    echo "  </div>
          </td>";

    $cellsInCurrentRow++;
}

// Fill in next month days
$remainingCells = 7 - $cellsInCurrentRow;
if ($remainingCells < 7) { // Only add if not already full row
    for ($i = 1; $i <= $remainingCells; $i++) {
        echo "<td class='faded-day'>$i</td>";
    }
}

// Only close the last <tr> — no extra </tr> if row is full
echo '</tr>';
echo '</table>';
?>