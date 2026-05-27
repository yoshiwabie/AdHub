<?php

function getCampaigns($conn, $staff_id = null){

    $sql = "
        SELECT 
            c.campaign_id,
            c.campaign_name,
            c.status,
            c.start_date,
            c.deadline,
            u.name AS staff_name
        FROM campaigns c
        LEFT JOIN users u 
        ON c.assigned_staff_id = u.user_id
    ";

    // OPTIONAL FILTER (for future role-based view)
    if($staff_id != null){
        $sql .= " WHERE c.assigned_staff_id = $staff_id";
    }

    return mysqli_query($conn, $sql);
}

function getCampaignsByStatus($conn, $status){

    $sql = "
        SELECT 
            c.campaign_id,
            c.campaign_name,
            c.status,
            c.start_date,
            c.deadline,
            u.name AS staff_name
        FROM campaigns c
        LEFT JOIN users u 
        ON c.assigned_staff_id = u.user_id
        WHERE c.status = '$status'
    ";

    return mysqli_query($conn, $sql);
}

function countTable($conn, $table){
    $sql = "SELECT COUNT(*) as total FROM $table";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['total'];
}

/* RECENT CAMPAIGNS */
function getRecentCampaigns($conn){
    return mysqli_query($conn, "
        SELECT campaign_id, campaign_name, status, deadline
        FROM campaigns
        ORDER BY campaign_id DESC
        LIMIT 5
    ");
}

/* APPROVED ASSETS */
function getApprovedAssets($conn){
    return mysqli_query($conn, "
        SELECT a.file_path, c.campaign_name
        FROM assets a
        JOIN milestones m ON a.milestone_id = m.milestone_id
        JOIN campaigns c ON m.campaign_id = c.campaign_id
        JOIN approvals ap ON ap.approval_id = (
            SELECT ap2.approval_id
            FROM approvals ap2
            WHERE ap2.milestone_id = m.milestone_id
            ORDER BY ap2.approval_id DESC
            LIMIT 1
        )
        WHERE ap.status = 'approved'
        ORDER BY a.asset_id DESC
        LIMIT 5
    ");
}

/* REVISION ASSETS */
function getRevisionAssets($conn){
    return mysqli_query($conn, "
        SELECT a.file_path, c.campaign_name
        FROM assets a
        JOIN milestones m ON a.milestone_id = m.milestone_id
        JOIN campaigns c ON m.campaign_id = c.campaign_id
        JOIN approvals ap ON ap.approval_id = (
            SELECT ap2.approval_id
            FROM approvals ap2
            WHERE ap2.milestone_id = m.milestone_id
            ORDER BY ap2.approval_id DESC
            LIMIT 1
        )
        WHERE ap.status = 'revision'
        ORDER BY a.asset_id DESC
        LIMIT 5
    ");
}

/* MILESTONE PROGRESS (simple percent calc) */
function getMilestoneProgress($conn){
    return mysqli_query($conn, "
        SELECT 
            c.campaign_name,
            COUNT(m.milestone_id) as total,
            SUM(CASE WHEN m.status='approved' THEN 1 ELSE 0 END) as done
        FROM campaigns c
        LEFT JOIN milestones m ON c.campaign_id = m.campaign_id
        GROUP BY c.campaign_id
    ");
}

?>