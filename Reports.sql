SELECT 
    f.facility_id,
    f.city,
    f.state,
    f.type AS facility_type,
    COUNT(p.tracking_number) AS packages_handled,
    SUM(p.postage) AS revenue_generated
FROM 
    Package p
    JOIN Facility f ON p.facility_id = f.facility_id
GROUP BY 
    f.facility_id, f.city, f.state, f.type
ORDER BY 
    packages_handled DESC;