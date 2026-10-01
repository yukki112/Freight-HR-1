<?php
// /includes/erm/erm_functions.php

/**
 * Category definitions
 */
function ermCategories() {
    return [
        'personal'           => ['label' => 'Personal Documents',     'icon' => 'fa-id-card',         'color' => '#3b82f6'],
        'employment'         => ['label' => 'Employment Documents',   'icon' => 'fa-file-contract',   'color' => '#0e4c92'],
        'government'         => ['label' => 'Government Documents',   'icon' => 'fa-landmark',        'color' => '#16a34a'],
        'educational'        => ['label' => 'Educational Records',    'icon' => 'fa-graduation-cap',  'color' => '#7c3aed'],
        'training'           => ['label' => 'Training Certificates',  'icon' => 'fa-certificate',     'color' => '#0ea5e9'],
        'driver'             => ['label' => 'Driver Documents',       'icon' => 'fa-truck',           'color' => '#f59e0b'],
        'medical'            => ['label' => 'Medical Records',        'icon' => 'fa-heart-pulse',     'color' => '#dc2626'],
        'performance'        => ['label' => 'Performance Records',    'icon' => 'fa-chart-line',      'color' => '#8b5cf6'],
        'disciplinary'       => ['label' => 'Disciplinary Records',   'icon' => 'fa-gavel',           'color' => '#991b1b'],
        'promotion_transfer' => ['label' => 'Promotion & Transfer',   'icon' => 'fa-arrow-trend-up',  'color' => '#059669'],
        'payroll'            => ['label' => 'Payroll Documents',      'icon' => 'fa-money-bill-wave', 'color' => '#ca8a04'],
        'leave'              => ['label' => 'Leave Records',          'icon' => 'fa-calendar-minus',  'color' => '#0891b2'],
        'separation'         => ['label' => 'Separation Records',     'icon' => 'fa-door-open',       'color' => '#64748b'],
        'other'              => ['label' => 'Other Records',          'icon' => 'fa-folder',          'color' => '#94a3b8'],
    ];
}

/**
 * Standard document types per category
 */
function ermStandardDocs() {
    return [
        'personal'    => ['Birth Certificate','Marriage Certificate','Valid ID (Passport)','Valid ID (Driver\'s License)','Valid ID (UMID)','Barangay Clearance','NBI Clearance','Police Clearance','Photo 2x2'],
        'employment'  => ['Employment Contract','Job Offer Letter','Appointment Paper','NDA','Company ID','Uniform Issuance Form','Equipment Custody Form'],
        'government'  => ['SSS ID / Number','PhilHealth ID / Number','Pag-IBIG ID / Number','TIN ID / Number','Government Mandated Forms'],
        'educational' => ['Diploma','Transcript of Records','Form 137','Certificate of Graduation','Board Rating / PRC License'],
        'training'    => ['Training Certificate','Seminar Certificate','Workshop Certificate','Professional Development Record'],
        'driver'      => ['Professional Driver\'s License','LTO OR/CR','Defensive Driving Cert','Safety Training Cert','Hazmat Cert'],
        'medical'     => ['Medical Certificate','Drug Test Result','Pre-employment Medical','Annual Physical Exam','Fit to Work Certificate'],
        'performance' => ['Performance Appraisal','KPI Scorecard','Evaluation Form','Commendation Letter','Improvement Plan'],
        'disciplinary'=> ['Warning Letter','Notice to Explain','Incident Report','Suspension Order','Hearing Minutes'],
        'promotion_transfer' => ['Promotion Letter','Transfer Order','Salary Adjustment Memo','Role Change Memo'],
        'payroll'     => ['Payslip Archive','Bank Account Details','Salary Structure','Tax Withholding Certificate'],
        'leave'       => ['Leave Records','Leave Credits Summary','Approved Leave Forms'],
        'separation'  => ['Resignation Letter','Clearance Form','Exit Interview','Final Pay Computation','Certificate of Employment'],
        'other'       => ['Miscellaneous Document'],
    ];
}

/**
 * Log an audit entry
 */
function ermAudit($pdo, $recordId, $employeeId, $action, $description = '') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO employee_records_audit
            (record_id, employee_id, action, description, performed_by, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $recordId ?: null,
            $employeeId,
            $action,
            $description,
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) { /* silent */ }
}

/**
 * Get overall ERM stats
 */
function ermStats($pdo) {
    return $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'verified') AS verified,
            SUM(status = 'pending') AS pending,
            SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS expired,
            SUM(expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS expiring_soon,
            SUM(is_archived = 1) AS archived
        FROM employee_records
    ")->fetch();
}

/**
 * Status pill CSS class
 */
function ermStatusClass($status) {
    return match($status) {
        'verified'  => 'verified',
        'pending'   => 'pending',
        'rejected'  => 'rejected',
        'expired'   => 'expired',
        'archived'  => 'archived',
        default     => 'pending'
    };
}
?>