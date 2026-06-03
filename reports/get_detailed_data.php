<?php
require_once '../db_connect.php';
require_once '../functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Get parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$facility_filter = isset($_GET['facility']) ? intval($_GET['facility']) : 0;
$driver_filter = isset($_GET['driver']) ? intval($_GET['driver']) : 0;
$destination_filter = isset($_GET['destination']) ? intval($_GET['destination']) : 0;
$shop_filter = isset($_GET['shop']) ? intval($_GET['shop']) : 0;
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Validate required parameters
if (empty($report_type) || empty($start_date) || empty($end_date)) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Initialize results array
$results = [];

try {
    // Different queries based on report type
    switch ($report_type) {
        case 'volume_by_origin':
            // Get individual packages that originated from each facility
            $query = "SELECT 
                        p.tracking_number,
                        p.weight,
                        p.size,
                        p.postage,
                        p.shipping_speed,
                        p.status,
                        p.timestamp_created as created_date,
                        f.facility_id,
                        f.city as origin_city,
                        f.state as origin_state,
                        f.type as facility_type,
                        CONCAT(c_sender.first_name, ' ', c_sender.last_name) as sender,
                        CONCAT(c_receiver.first_name, ' ', c_receiver.last_name) as receiver
                      FROM 
                        Package p
                      JOIN 
                        Facility f ON p.facility_id = f.facility_id
                      LEFT JOIN
                        Customer c_sender ON p.sender_id = c_sender.user_id
                      LEFT JOIN
                        Customer c_receiver ON p.receiver_id = c_receiver.user_id
                      WHERE 
                        p.timestamp_created BETWEEN ? AND ?
                      ORDER BY
                        f.facility_id, p.timestamp_created DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            break;
            
        case 'volume_by_destination':
            // Get individual packages by destination city/state
            $query = "SELECT 
                        p.tracking_number,
                        p.weight,
                        p.size,
                        p.postage,
                        p.shipping_speed,
                        p.status,
                        p.timestamp_created as created_date,
                        c.city as destination_city,
                        c.state as destination_state,
                        CONCAT(c_sender.first_name, ' ', c_sender.last_name) as sender,
                        CONCAT(c.first_name, ' ', c.last_name) as receiver
                      FROM 
                        Package p
                      JOIN 
                        Customer c ON p.receiver_id = c.user_id
                      LEFT JOIN
                        Customer c_sender ON p.sender_id = c_sender.user_id
                      WHERE 
                        p.timestamp_created BETWEEN ? AND ?
                      ORDER BY
                        c.state, c.city, p.timestamp_created DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            break;
            
        case 'busiest_routes':
            // Get individual packages that traveled on specific routes
            $query = "SELECT 
                        p.tracking_number,
                        p.weight,
                        p.size,
                        p.postage,
                        p.shipping_speed,
                        p.status,
                        p.timestamp_created as created_date,
                        f1.city as origin_city, 
                        f1.state as origin_state,
                        f2.city as dest_city,
                        f2.state as dest_state,
                        t.trip_id,
                        t.depart_time,
                        t.arrival_time,
                        TIMESTAMPDIFF(MINUTE, t.depart_time, t.arrival_time) AS trip_duration_minutes,
                        CONCAT(e.first_name, ' ', e.last_name) as driver
                      FROM 
                        Trip_Package tp
                      JOIN 
                        Trip t ON tp.trip_id = t.trip_id
                      JOIN 
                        Facility f1 ON t.depart_facility_id = f1.facility_id
                      JOIN 
                        Facility f2 ON t.arrive_facility_id = f2.facility_id
                      JOIN 
                        Package p ON tp.tracking_number = p.tracking_number
                      LEFT JOIN
                        Employee e ON t.employee_id = e.user_id
                      WHERE 
                        p.timestamp_created BETWEEN ? AND ?
                      ORDER BY
                        f1.city, f1.state, f2.city, f2.state, p.timestamp_created DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            break;
            
        case 'distribution_by_size':
            // Get individual packages by size
            $query = "SELECT 
                        p.tracking_number,
                        p.weight,
                        p.size,
                        p.postage,
                        p.shipping_speed,
                        p.status,
                        p.timestamp_created as created_date,
                        CONCAT(c_sender.first_name, ' ', c_sender.last_name) as sender,
                        CONCAT(c_receiver.first_name, ' ', c_receiver.last_name) as receiver,
                        f.city as origin_city,
                        f.state as origin_state
                      FROM 
                        Package p
                      LEFT JOIN
                        Customer c_sender ON p.sender_id = c_sender.user_id
                      LEFT JOIN
                        Customer c_receiver ON p.receiver_id = c_receiver.user_id
                      LEFT JOIN
                        Facility f ON p.facility_id = f.facility_id
                      WHERE 
                        p.timestamp_created BETWEEN ?  AND ?";
            
            // Add facility filter if provided
            if ($facility_filter > 0) {
                $query .= " AND p.facility_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
            } else {
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $start_date, $end_date);
            }
            break;
            
        case 'distribution_by_weight':
            // Get individual packages by weight range
            $query = "SELECT 
                        p.tracking_number,
                        p.weight,
                        CASE
                            WHEN weight <= 1 THEN 'Under 1 lb'
                            WHEN weight <= 5 THEN '1-5 lbs'
                            WHEN weight <= 10 THEN '5-10 lbs'
                            WHEN weight <= 20 THEN '10-20 lbs'
                            ELSE 'Over 20 lbs'
                        END as weight_range,
                        p.size,
                        p.postage,
                        p.shipping_speed,
                        p.status,
                        p.timestamp_created as created_date,
                        CONCAT(c_sender.first_name, ' ', c_sender.last_name) as sender,
                        CONCAT(c_receiver.first_name, ' ', c_receiver.last_name) as receiver,
                        f.city as origin_city,
                        f.state as origin_state
                      FROM 
                        Package p
                      LEFT JOIN
                        Customer c_sender ON p.sender_id = c_sender.user_id
                      LEFT JOIN
                        Customer c_receiver ON p.receiver_id = c_receiver.user_id
                      LEFT JOIN
                        Facility f ON p.facility_id = f.facility_id
                      WHERE 
                        p.timestamp_created BETWEEN ? AND ?";
            
            // Add facility filter if provided
            if ($facility_filter > 0) {
                $query .= " AND p.facility_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
            } else {
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $start_date, $end_date);
            }
            break;
            
        case 'top_customers':
            // Get individual transactions for top customers
            $query = "SELECT 
                        c.user_id,
                        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                        p.tracking_number,
                        p.weight,
                        p.size,
                        pp.amount as postage_paid,
                        pp.payment_method,
                        pp.payment_date,
                        p.shipping_speed,
                        p.status,
                        f.city as facility_city,
                        f.state as facility_state
                      FROM 
                        Package_Payment pp
                      JOIN 
                        Package p ON pp.package_id = p.tracking_number
                      JOIN 
                        Customer c ON pp.user_id = c.user_id
                      LEFT JOIN
                        Facility f ON pp.facility_id = f.facility_id
                      WHERE 
                        pp.payment_date BETWEEN ? AND ?";
            
            // Add facility filter if provided
            if ($facility_filter > 0) {
                $query .= " AND pp.facility_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
            } else {
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $start_date, $end_date);
            }
            break;
            
        case 'route_details':
            // Get individual packages on routes
            $query = "SELECT 
                        t.trip_id,
                        t.trip_type,
                        t.depart_time,
                        t.arrival_time,
                        TIMESTAMPDIFF(MINUTE, t.depart_time, t.arrival_time) AS duration_minutes,
                        CONCAT(e.first_name, ' ', e.last_name) AS driver_name,
                        df.city AS origin_city,
                        df.state AS origin_state,
                        af.city AS destination_city,
                        af.state AS destination_state,
                        p.tracking_number,
                        p.weight,
                        p.size,
                        p.shipping_speed,
                        p.status,
                        CONCAT(c_sender.first_name, ' ', c_sender.last_name) AS sender,
                        CONCAT(c_receiver.first_name, ' ', c_receiver.last_name) AS receiver,
                        (SELECT th.timestamp FROM Tracking_History th 
                         WHERE th.tracking_number = p.tracking_number AND th.status = 'Delivered' 
                         ORDER BY th.timestamp DESC LIMIT 1) AS delivery_date,
                        (SELECT th.timestamp FROM Tracking_History th 
                         WHERE th.tracking_number = p.tracking_number AND th.status = 'Lost' 
                         ORDER BY th.timestamp DESC LIMIT 1) AS lost_date
                      FROM 
                        Trip t
                      LEFT JOIN 
                        Employee e ON t.employee_id = e.user_id
                      LEFT JOIN 
                        Facility df ON t.depart_facility_id = df.facility_id
                      LEFT JOIN 
                        Facility af ON t.arrive_facility_id = af.facility_id
                      LEFT JOIN 
                        Trip_Package tp ON t.trip_id = tp.trip_id
                      LEFT JOIN 
                        Package p ON tp.tracking_number = p.tracking_number
                      LEFT JOIN
                        Customer c_sender ON p.sender_id = c_sender.user_id
                      LEFT JOIN
                        Customer c_receiver ON p.receiver_id = c_receiver.user_id
                      WHERE 
                        t.depart_time BETWEEN ? AND ? 
                        AND t.trip_type = 'Delivery'";
            
            // Add filters if provided
            $params = [$start_date, $end_date];
            $types = "ss";
            
            if ($driver_filter > 0) {
                $query .= " AND t.employee_id = ?";
                $params[] = $driver_filter;
                $types .= "i";
            }
            
            if ($destination_filter > 0) {
                $query .= " AND t.arrive_facility_id = ?";
                $params[] = $destination_filter;
                $types .= "i";
            }
            
            $query .= " ORDER BY t.depart_time DESC, t.trip_id";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            break;
            
        case 'sales_transactions':
            // Get individual sales transactions with details
            $query = "SELECT 
                        st.transaction_id,
                        st.transaction_date,
                        s.shop_name,
                        f.city as facility_city,
                        f.state as facility_state,
                        ss.sale_id,
                        i.name as item_name,
                        i.category,
                        i.price_wholesale as wholesale_price,
                        i.sale_price as retail_price,
                        ss.quantity,
                        ss.sale_amount,
                        (ss.sale_amount - (i.price_wholesale * ss.quantity)) as estimated_profit,
                        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                        st.payment_method,
                        st.transaction_status
                      FROM 
                        Shop_Sale ss
                      JOIN 
                        Shop_Transaction st ON ss.transaction_id = st.transaction_id
                      JOIN 
                        Shop s ON ss.shop_id = s.shop_id
                      JOIN 
                        Items i ON ss.item_id = i.item_id
                      JOIN 
                        Facility f ON s.facility_id = f.facility_id
                      LEFT JOIN
                        Customer c ON st.user_id = c.user_id
                      WHERE 
                        ss.sale_date BETWEEN ? AND ?";
            
            // Add filters if provided
            $params = [$start_date, $end_date];
            $types = "ss";
            
            if ($shop_filter > 0) {
                $query .= " AND ss.shop_id = ?";
                $params[] = $shop_filter;
                $types .= "i";
            }
            
            if (!empty($category_filter)) {
                $query .= " AND i.category = ?";
                $params[] = $category_filter;
                $types .= "s";
            }
            
            $query .= " ORDER BY st.transaction_date DESC, st.transaction_id, ss.sale_id";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid report type']);
            exit;
    }
    
    // Execute query and return results
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all results as an associative array
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    // Limit results to avoid performance issues
    if (count($results) > 1000) {
        $results = array_slice($results, 0, 1000);
    }
    
    // Return the results as JSON
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
} 