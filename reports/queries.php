<?php

$item_sql = "
    SELECT
        i.name AS item_name,
        SUM(ss.quantity) AS total_quantity,
        i.sale_price,
        SUM(ss.sale_amount) AS total_revenue,
        i.price_wholesale,
        SUM((i.sale_price - i.price_wholesale) * ss.quantity) AS total_profit
    FROM
        Shop_Sale ss
    JOIN
        Items i ON ss.item_id = i.item_id
    JOIN
        Shop s ON ss.shop_id = s.shop_id
    JOIN
        Facility f ON s.facility_id = f.facility_id
    JOIN
        Shop_Transaction st ON ss.transaction_id = st.transaction_id
    WHERE
        $where_clause
    GROUP BY
        i.item_id
    ORDER BY
        i.name ASC
";

$facility_sql = "
    SELECT
        f.facility_id,
        f.city,
        f.type,
        SUM(ss.sale_amount) AS total_revenue,
        SUM(i.price_wholesale * ss.quantity) AS total_cost,
        (SUM(ss.sale_amount) - SUM(i.price_wholesale * ss.quantity)) AS net_profit
    FROM
        Shop_Sale ss
    JOIN
        Items i ON ss.item_id = i.item_id
    JOIN
        Shop s ON ss.shop_id = s.shop_id
    JOIN
        Facility f ON s.facility_id = f.facility_id
    JOIN
        Shop_Transaction st ON ss.transaction_id = st.transaction_id
    WHERE
        $where_clause
    GROUP BY
        f.facility_id
    ORDER BY
        net_profit DESC
";

$company_sql = "
    SELECT 
        SUM(ss.sale_amount) AS total_revenue,
        SUM(i.price_wholesale * ss.quantity) AS total_cost,
        (SUM(ss.sale_amount) - SUM(i.price_wholesale * ss.quantity)) AS total_profit
    FROM
        Shop_Sale ss
    JOIN
        Items i ON ss.item_id = i.item_id
    JOIN
        Shop s ON ss.shop_id = s.shop_id
    JOIN
        Facility f ON s.facility_id = f.facility_id
    JOIN
        Shop_Transaction st ON ss.transaction_id = st.transaction_id
    WHERE
        $where_clause
";


$package_payment_sql = "SELECT 
    pp.payment_id, 
    pp.package_id as tracking_number, 
    pp.amount, 
    pp.payment_method,
    pp.transaction_status,
    pp.invoice_number,
    pp.payment_date,
    c.first_name, 
    c.last_name,
    p.weight,
    p.size,
    p.status as package_status,
    f.city as facility_city
FROM 
    Package_Payment pp
LEFT JOIN 
    Customer c ON pp.user_id = c.user_id
LEFT JOIN 
    Package p ON pp.package_id = p.tracking_number
LEFT JOIN
    Facility f ON pp.facility_id = f.facility_id
WHERE 
    pp.payment_date BETWEEN ? AND ?
" . ($facility_filter > 0 ? " AND pp.facility_id = $facility_filter" : "") . 
(($facility_filter == 0) ? " AND (f.type = 'Post Office' OR f.facility_id IS NULL)" : "") . "
ORDER BY 
    pp.payment_date DESC";

$payment_summary_sql = "SELECT 
    pp.payment_method,
    COUNT(*) as payment_count,
    SUM(pp.amount) as total_amount,
    pp.transaction_status,
    COUNT(DISTINCT pp.user_id) as customer_count,
    pp.package_id
FROM 
    Package_Payment pp
LEFT JOIN 
    Package p ON pp.package_id = p.tracking_number
LEFT JOIN
    Facility f ON pp.facility_id = f.facility_id
WHERE 
    pp.payment_date BETWEEN ? AND ?
" . ($facility_filter > 0 ? " AND pp.facility_id = $facility_filter" : "") . 
(($facility_filter == 0) ? " AND (f.type = 'Post Office' OR f.facility_id IS NULL)" : "") . "
GROUP BY 
    pp.payment_method, pp.transaction_status
ORDER BY 
    total_amount DESC";