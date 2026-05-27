<?php

function getLeastBusyStaff($conn){


    $query = mysqli_query($conn, "
        SELECT 
            u.user_id,
            COUNT(c.campaign_id) AS workload
        FROM users u
        LEFT JOIN campaigns c 
            ON u.user_id = c.assigned_staff_id
            AND c.status IN ('planning','active','review')
        WHERE u.role = 'staff'
        GROUP BY u.user_id
        ORDER BY workload ASC
        LIMIT 1
    ");

    $staff = mysqli_fetch_assoc($query);

    return $staff['user_id'] ?? null;
}

?>