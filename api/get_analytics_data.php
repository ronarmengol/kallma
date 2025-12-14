<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

// Check authentication
if (!isLoggedIn() || (!isAdmin() && !isMasseuse())) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : 'all';

$data = [];

// Get masseuse ID if logged in as masseuse
$logged_in_masseuse_id = null;
if (isMasseuse()) {
    $logged_in_masseuse_id = getMasseuseIdByUserId($conn, $_SESSION['user_id']);
}

// 1. INACTIVE CUSTOMERS
if ($type === 'all' || $type === 'inactive_customers') {
    $inactive_customers = [];
    
    // Get customers who haven't booked in 30, 60, or 90 days
    $sql = "SELECT 
                u.id,
                u.name,
                u.mobile,
                MAX(b.booking_date) as last_visit,
                COUNT(b.id) as total_visits,
                DATEDIFF(CURDATE(), MAX(b.booking_date)) as days_since_visit,
                (SELECT s.name FROM bookings b2 
                 JOIN services s ON b2.service_id = s.id 
                 WHERE b2.user_id = u.id 
                 GROUP BY s.id 
                 ORDER BY COUNT(*) DESC 
                 LIMIT 1) as favorite_service
            FROM users u
            JOIN bookings b ON u.id = b.user_id
            WHERE u.role = 'customer'
            AND b.status IN ('completed', 'confirmed')
            GROUP BY u.id
            HAVING days_since_visit >= 30
            ORDER BY days_since_visit DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $category = 'inactive_30';
            if ($row['days_since_visit'] >= 90) {
                $category = 'inactive_90';
            } elseif ($row['days_since_visit'] >= 60) {
                $category = 'inactive_60';
            }
            
            $inactive_customers[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'last_visit' => $row['last_visit'],
                'days_since_visit' => (int)$row['days_since_visit'],
                'total_visits' => (int)$row['total_visits'],
                'favorite_service' => $row['favorite_service'],
                'category' => $category
            ];
        }
    }
    
    $data['inactive_customers'] = $inactive_customers;
}

// 2. BOOKING TRENDS
if ($type === 'all' || $type === 'booking_trends') {
    $trends = [];
    
    // Today's bookings
    $today_sql = "SELECT COUNT(*) as count FROM bookings WHERE booking_date = CURDATE()";
    if ($logged_in_masseuse_id) {
        $today_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $today_count = $conn->query($today_sql)->fetch_assoc()['count'];
    
    // Yesterday's bookings
    $yesterday_sql = "SELECT COUNT(*) as count FROM bookings WHERE booking_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    if ($logged_in_masseuse_id) {
        $yesterday_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $yesterday_count = $conn->query($yesterday_sql)->fetch_assoc()['count'];
    
    // This week's bookings
    $week_sql = "SELECT COUNT(*) as count FROM bookings WHERE YEARWEEK(booking_date, 1) = YEARWEEK(CURDATE(), 1)";
    if ($logged_in_masseuse_id) {
        $week_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $week_count = $conn->query($week_sql)->fetch_assoc()['count'];
    
    // Last week's bookings
    $last_week_sql = "SELECT COUNT(*) as count FROM bookings WHERE YEARWEEK(booking_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
    if ($logged_in_masseuse_id) {
        $last_week_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $last_week_count = $conn->query($last_week_sql)->fetch_assoc()['count'];
    
    // This month's bookings
    $month_sql = "SELECT COUNT(*) as count FROM bookings WHERE YEAR(booking_date) = YEAR(CURDATE()) AND MONTH(booking_date) = MONTH(CURDATE())";
    if ($logged_in_masseuse_id) {
        $month_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $month_count = $conn->query($month_sql)->fetch_assoc()['count'];
    
    // Last month's bookings
    $last_month_sql = "SELECT COUNT(*) as count FROM bookings WHERE YEAR(booking_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(booking_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    if ($logged_in_masseuse_id) {
        $last_month_sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    $last_month_count = $conn->query($last_month_sql)->fetch_assoc()['count'];
    
    $trends = [
        'today' => [
            'count' => (int)$today_count,
            'previous' => (int)$yesterday_count,
            'change' => $yesterday_count > 0 ? round((($today_count - $yesterday_count) / $yesterday_count) * 100, 1) : 0
        ],
        'week' => [
            'count' => (int)$week_count,
            'previous' => (int)$last_week_count,
            'change' => $last_week_count > 0 ? round((($week_count - $last_week_count) / $last_week_count) * 100, 1) : 0
        ],
        'month' => [
            'count' => (int)$month_count,
            'previous' => (int)$last_month_count,
            'change' => $last_month_count > 0 ? round((($month_count - $last_month_count) / $last_month_count) * 100, 1) : 0
        ]
    ];
    
    // Daily Trend (Last 7 Days)
    $daily_trend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_sql = "SELECT COUNT(*) as count FROM bookings WHERE booking_date = '$date'";
        if ($logged_in_masseuse_id) {
            $day_sql .= " AND masseuse_id = $logged_in_masseuse_id";
        }
        $count = $conn->query($day_sql)->fetch_assoc()['count'];
        $daily_trend[] = [
            'date' => date('M d', strtotime($date)),
            'count' => (int)$count
        ];
    }
    $trends['daily_trend'] = $daily_trend;
    
    $data['booking_trends'] = $trends;
}

// 3. PEAK HOURS HEATMAP
if ($type === 'all' || $type === 'peak_hours') {
    $heatmap = [];
    
    // Initialize heatmap array
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($days as $day) {
        $heatmap[$day] = array_fill(0, 24, 0);
    }
    
    // Get booking counts by day and hour (last 30 days)
    $sql = "SELECT 
                DAYNAME(booking_date) as day_name,
                HOUR(booking_time) as hour,
                COUNT(*) as count
            FROM bookings
            WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    if ($logged_in_masseuse_id) {
        $sql .= " AND masseuse_id = $logged_in_masseuse_id";
    }
    
    $sql .= " GROUP BY day_name, hour";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $day = $row['day_name'];
            $hour = (int)$row['hour'];
            $count = (int)$row['count'];
            
            if (isset($heatmap[$day])) {
                $heatmap[$day][$hour] = $count;
            }
        }
    }
    
    $data['peak_hours'] = $heatmap;
}

// 4. SERVICE POPULARITY
if ($type === 'all' || $type === 'service_popularity') {
    $services = [];
    
    // Get service booking counts (last 30 days)
    $sql = "SELECT 
                s.id,
                s.name,
                COUNT(b.id) as booking_count,
                (SELECT COUNT(*) FROM bookings b2 
                 WHERE b2.service_id = s.id 
                 AND b2.booking_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                 AND b2.booking_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as previous_count
            FROM services s
            LEFT JOIN bookings b ON s.id = b.service_id 
                AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    if ($logged_in_masseuse_id) {
        $sql .= " AND b.masseuse_id = $logged_in_masseuse_id";
    }
    
    $sql .= " GROUP BY s.id, s.name
              ORDER BY booking_count DESC
              LIMIT 10";
    
    $result = $conn->query($sql);
    $total_bookings = 0;
    
    if ($result) {
        $temp_services = [];
        while ($row = $result->fetch_assoc()) {
            $temp_services[] = $row;
            $total_bookings += (int)$row['booking_count'];
        }
        
        foreach ($temp_services as $service) {
            $booking_count = (int)$service['booking_count'];
            $previous_count = (int)$service['previous_count'];
            
            $services[] = [
                'id' => $service['id'],
                'name' => $service['name'],
                'booking_count' => $booking_count,
                'percentage' => $total_bookings > 0 ? round(($booking_count / $total_bookings) * 100, 1) : 0,
                'trend' => $previous_count > 0 ? round((($booking_count - $previous_count) / $previous_count) * 100, 1) : 0
            ];
        }
    }
    
    $data['service_popularity'] = $services;
}

// 5. CUSTOMER SEGMENTATION (Phase 2)
if ($type === 'all' || $type === 'customer_segmentation') {
    $segmentation = [
        'vip' => [],
        'at_risk' => [],
        'one_time' => []
    ];
    
    // VIP Customers (10+ bookings)
    $vip_sql = "SELECT u.id, u.name, u.mobile, COUNT(b.id) as total_bookings
                FROM users u
                JOIN bookings b ON u.id = b.user_id
                WHERE u.role = 'customer'
                GROUP BY u.id
                HAVING total_bookings >= 10
                ORDER BY total_bookings DESC";
    
    $result = $conn->query($vip_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $segmentation['vip'][] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'total_bookings' => (int)$row['total_bookings']
            ];
        }
    }
    
    // At-Risk Customers (3+ bookings but inactive 30+ days)
    $at_risk_sql = "SELECT u.id, u.name, u.mobile, 
                    COUNT(b.id) as total_bookings,
                    MAX(b.booking_date) as last_visit,
                    DATEDIFF(CURDATE(), MAX(b.booking_date)) as days_since_visit
                    FROM users u
                    JOIN bookings b ON u.id = b.user_id
                    WHERE u.role = 'customer'
                    AND b.status IN ('completed', 'confirmed')
                    GROUP BY u.id
                    HAVING total_bookings >= 3 AND days_since_visit >= 30
                    ORDER BY days_since_visit DESC";
    
    $result = $conn->query($at_risk_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $segmentation['at_risk'][] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'total_bookings' => (int)$row['total_bookings'],
                'last_visit' => $row['last_visit'],
                'days_since_visit' => (int)$row['days_since_visit']
            ];
        }
    }
    
    // One-Time Customers (exactly 1 booking)
    $one_time_sql = "SELECT u.id, u.name, u.mobile, 
                     MAX(b.booking_date) as booking_date,
                     DATEDIFF(CURDATE(), MAX(b.booking_date)) as days_ago
                     FROM users u
                     JOIN bookings b ON u.id = b.user_id
                     WHERE u.role = 'customer'
                     GROUP BY u.id
                     HAVING COUNT(b.id) = 1
                     ORDER BY booking_date DESC";
    
    $result = $conn->query($one_time_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $segmentation['one_time'][] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'booking_date' => $row['booking_date'],
                'days_ago' => (int)$row['days_ago']
            ];
        }
    }
    
    $data['customer_segmentation'] = $segmentation;
}

// 6. MASSEUSE PERFORMANCE (Phase 2)
if ($type === 'all' || $type === 'masseuse_performance') {
    $performance = [];
    
    // Get all masseuses
    $masseuses_sql = "SELECT id, name FROM masseuses ORDER BY name";
    $masseuses_result = $conn->query($masseuses_sql);
    
    if ($masseuses_result) {
        while ($masseuse = $masseuses_result->fetch_assoc()) {
            $masseuse_id = $masseuse['id'];
            
            // Bookings last 30 days
            $bookings_sql = "SELECT COUNT(*) as count FROM bookings 
                            WHERE masseuse_id = $masseuse_id 
                            AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $bookings_count = $conn->query($bookings_sql)->fetch_assoc()['count'];
            
            // Repeat customers (customers with 2+ bookings with this masseuse)
            $repeat_sql = "SELECT COUNT(DISTINCT user_id) as repeat_count
                          FROM (
                              SELECT user_id, COUNT(*) as booking_count
                              FROM bookings
                              WHERE masseuse_id = $masseuse_id
                              GROUP BY user_id
                              HAVING booking_count >= 2
                          ) as repeat_customers";
            $repeat_count = $conn->query($repeat_sql)->fetch_assoc()['repeat_count'];
            
            // Total unique customers
            $total_customers_sql = "SELECT COUNT(DISTINCT user_id) as total
                                   FROM bookings
                                   WHERE masseuse_id = $masseuse_id";
            $total_customers = $conn->query($total_customers_sql)->fetch_assoc()['total'];
            
            // Calculate repeat rate
            $repeat_rate = $total_customers > 0 ? round(($repeat_count / $total_customers) * 100, 1) : 0;
            
            // Average bookings per day (last 30 days)
            $avg_per_day = round($bookings_count / 30, 1);
            
            $performance[] = [
                'id' => $masseuse_id,
                'name' => $masseuse['name'],
                'bookings_30_days' => (int)$bookings_count,
                'repeat_customer_rate' => $repeat_rate,
                'avg_bookings_per_day' => $avg_per_day,
                'total_customers' => (int)$total_customers
            ];
        }
    }
    
    // Sort by bookings count
    usort($performance, function($a, $b) {
        return $b['bookings_30_days'] - $a['bookings_30_days'];
    });
    
    // Add ranking
    foreach ($performance as $index => &$perf) {
        $perf['rank'] = $index + 1;
    }
    
    $data['masseuse_performance'] = $performance;
}

// 7. CANCELLATION TRACKING (Phase 2)
if ($type === 'all' || $type === 'cancellation_tracking') {
    $cancellations = [];
    
    // Total cancellations last 30 days
    $total_cancelled_sql = "SELECT COUNT(*) as count FROM bookings 
                           WHERE status = 'cancelled' 
                           AND booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $total_cancelled = $conn->query($total_cancelled_sql)->fetch_assoc()['count'];
    
    // Total bookings last 30 days
    $total_bookings_sql = "SELECT COUNT(*) as count FROM bookings 
                          WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $total_bookings = $conn->query($total_bookings_sql)->fetch_assoc()['count'];
    
    // Cancellation rate
    $cancellation_rate = $total_bookings > 0 ? round(($total_cancelled / $total_bookings) * 100, 1) : 0;
    
    // Top cancelling customers
    $top_cancellers_sql = "SELECT u.name, u.mobile, COUNT(*) as cancellation_count
                          FROM bookings b
                          JOIN users u ON b.user_id = u.id
                          WHERE b.status = 'cancelled'
                          AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                          GROUP BY u.id
                          ORDER BY cancellation_count DESC
                          LIMIT 5";
    
    $top_cancellers = [];
    $result = $conn->query($top_cancellers_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $top_cancellers[] = [
                'name' => $row['name'],
                'mobile' => $row['mobile'],
                'cancellation_count' => (int)$row['cancellation_count']
            ];
        }
    }
    
    // Services with most cancellations
    $service_cancellations_sql = "SELECT s.name, COUNT(*) as cancellation_count
                                  FROM bookings b
                                  JOIN services s ON b.service_id = s.id
                                  WHERE b.status = 'cancelled'
                                  AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                                  GROUP BY s.id
                                  ORDER BY cancellation_count DESC
                                  LIMIT 5";
    
    $service_cancellations = [];
    $result = $conn->query($service_cancellations_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $service_cancellations[] = [
                'service_name' => $row['name'],
                'cancellation_count' => (int)$row['cancellation_count']
            ];
        }
    }
    
    $cancellations = [
        'total_cancelled' => (int)$total_cancelled,
        'total_bookings' => (int)$total_bookings,
        'cancellation_rate' => $cancellation_rate,
        'top_cancellers' => $top_cancellers,
        'service_cancellations' => $service_cancellations
    ];
    
    $data['cancellation_tracking'] = $cancellations;
}

// 8. SUMMARY REPORT (Phase 3)
if ($type === 'all' || $type === 'summary_report') {
    $summary = [];
    
    // This week
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date >= '$week_start'")->fetch_assoc()['count'];
    $last_week_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date >= DATE_SUB('$week_start', INTERVAL 1 WEEK) AND booking_date < '$week_start'")->fetch_assoc()['count'];
    
    // This month
    $month_start = date('Y-m-01');
    $month_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date >= '$month_start'")->fetch_assoc()['count'];
    $last_month_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date >= DATE_SUB('$month_start', INTERVAL 1 MONTH) AND booking_date < '$month_start'")->fetch_assoc()['count'];
    
    $summary = [
        'week' => [
            'bookings' => (int)$week_bookings,
            'previous' => (int)$last_week_bookings,
            'change' => $last_week_bookings > 0 ? round((($week_bookings - $last_week_bookings) / $last_week_bookings) * 100, 1) : 0
        ],
        'month' => [
            'bookings' => (int)$month_bookings,
            'previous' => (int)$last_month_bookings,
            'change' => $last_month_bookings > 0 ? round((($month_bookings - $last_month_bookings) / $last_month_bookings) * 100, 1) : 0
        ]
    ];
    
    $data['summary_report'] = $summary;
}

// 9. YEAR-OVER-YEAR COMPARISON (Phase 3)
if ($type === 'all' || $type === 'year_over_year') {
    $yoy = [];
    $current_year = date('Y');
    $previous_year = $current_year - 1;
    
    for ($month = 1; $month <= 12; $month++) {
        $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
        
        $current_count = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE YEAR(booking_date) = $current_year AND MONTH(booking_date) = $month")->fetch_assoc()['count'];
        $previous_count = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE YEAR(booking_date) = $previous_year AND MONTH(booking_date) = $month")->fetch_assoc()['count'];
        
        $yoy[] = [
            'month' => date('M', mktime(0, 0, 0, $month, 1)),
            'current_year' => (int)$current_count,
            'previous_year' => (int)$previous_count,
            'change' => $previous_count > 0 ? round((($current_count - $previous_count) / $previous_count) * 100, 1) : 0
        ];
    }
    
    $data['year_over_year'] = $yoy;
}

// 10. SERVICE AFFINITY (Phase 3)
if ($type === 'all' || $type === 'service_affinity') {
    $affinity = [];
    
    // Find service pairs booked by same customer
    $sql = "SELECT s1.name as service1, s2.name as service2, COUNT(*) as pair_count
            FROM bookings b1
            JOIN bookings b2 ON b1.user_id = b2.user_id AND b1.id < b2.id
            JOIN services s1 ON b1.service_id = s1.id
            JOIN services s2 ON b2.service_id = s2.id
            WHERE s1.id != s2.id
            GROUP BY s1.id, s2.id
            ORDER BY pair_count DESC
            LIMIT 10";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $affinity[] = [
                'service1' => $row['service1'],
                'service2' => $row['service2'],
                'count' => (int)$row['pair_count']
            ];
        }
    }
    
    $data['service_affinity'] = $affinity;
}

// 11. SEASONAL PATTERNS (Phase 3)
if ($type === 'all' || $type === 'seasonal_patterns') {
    $patterns = [];
    
    for ($month = 1; $month <= 12; $month++) {
        $count = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE MONTH(booking_date) = $month")->fetch_assoc()['count'];
        
        $patterns[] = [
            'month' => date('F', mktime(0, 0, 0, $month, 1)),
            'bookings' => (int)$count
        ];
    }
    
    // Find peak and slow months
    $max_bookings = max(array_column($patterns, 'bookings'));
    $min_bookings = min(array_column($patterns, 'bookings'));
    
    foreach ($patterns as &$pattern) {
        if ($pattern['bookings'] == $max_bookings) {
            $pattern['type'] = 'peak';
        } elseif ($pattern['bookings'] == $min_bookings) {
            $pattern['type'] = 'slow';
        } else {
            $pattern['type'] = 'normal';
        }
    }
    
    $data['seasonal_patterns'] = $patterns;
}

// 12. CUSTOMER PREFERENCES (Phase 3)
if ($type === 'all' || $type === 'customer_preferences') {
    $preferences = [];
    
    // Preferred time slots
    $time_prefs_sql = "SELECT HOUR(booking_time) as hour, COUNT(*) as count
                       FROM bookings
                       GROUP BY hour
                       ORDER BY count DESC
                       LIMIT 5";
    
    $time_prefs = [];
    $result = $conn->query($time_prefs_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $time_prefs[] = [
                'time' => $row['hour'] . ':00',
                'bookings' => (int)$row['count']
            ];
        }
    }
    
    // Preferred days
    $day_prefs_sql = "SELECT DAYNAME(booking_date) as day, COUNT(*) as count
                      FROM bookings
                      GROUP BY day
                      ORDER BY count DESC";
    
    $day_prefs = [];
    $result = $conn->query($day_prefs_sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $day_prefs[] = [
                'day' => $row['day'],
                'bookings' => (int)$row['count']
            ];
        }
    }
    
    $preferences = [
        'time_slots' => $time_prefs,
        'days' => $day_prefs
    ];
    
    $data['customer_preferences'] = $preferences;
}

echo json_encode($data);
$conn->close();
?>
