<?php
/**
 * includes/assessment_rules.php
 * Determines if an applicant's role/department requires an assessment
 * before proceeding to the initial interview.
 */

if (!function_exists('requiresAssessment')) {
    /**
     * Check if the given department + position requires an assessment
     * 
     * @param string $department  Department code (e.g., 'transportation', 'hr')
     * @param string $position    Position title (e.g., 'Driver', 'HR Manager')
     * @return bool
     */
    function requiresAssessment($department, $position) {
        $department = strtolower(trim($department ?? ''));
        $position   = strtolower(trim($position ?? ''));

        // ---------- 1. Departments that NEVER require assessment ----------
        $noAssessmentDepartments = [
            'hr',
            'human_resources',
            'human resources',
            'administration',
            'admin',
        ];
        if (in_array($department, $noAssessmentDepartments, true)) {
            return false;
        }

        // ---------- 2. Position keyword exemptions (any department) ----------
        // Executives / Directors / Managers -> no assessment
        $noAssessmentKeywords = [
            'chief', 'coo', 'cfo', 'chro', 'cso', 'cio', 'ceo',
            'director',
            'manager',
            'general counsel',
        ];
        foreach ($noAssessmentKeywords as $kw) {
            if (strpos($position, $kw) !== false) {
                return false;
            }
        }

        // ---------- 3. Specific position exemptions ----------
        // Assistant / Helper roles that don't need assessment
        $noAssessmentPositions = [
            'customer service assistant',
            'sales assistant',
            'procurement assistant',
            'compliance assistant',
            'it support staff',
            'administrative assistant',
            'administrative staff',
            'admin assistant',
            'admin staff',
            'warehouse helper',
            'driver assistant',
            'driver helper',
            'driver assistant / helper',
        ];
        foreach ($noAssessmentPositions as $p) {
            if (strpos($position, $p) !== false) {
                return false;
            }
        }

        // ---------- 4. Departments that DO require assessment ----------
        $assessmentDepartments = [
            'operations',
            'transportation',
            'fleet',
            'warehouse',
            'customer_service',
            'customer service',
            'sales',
            'sales & business development',
            'finance',
            'finance & accounting',
            'accounting',
            'procurement',
            'compliance',
            'compliance / legal & risk',
            'legal',
            'it',
            'safety_security',
            'safety & security',
            'safety',
            'security',
        ];

        if (in_array($department, $assessmentDepartments, true)) {
            return true;
        }

        // Default: if unknown department, require assessment (safer)
        return true;
    }

    /**
     * Get a friendly label for assessment requirement
     */
    function getAssessmentLabel($requires) {
        return $requires ? 'Assessment Required' : 'Direct to Interview';
    }

    /**
     * Get the assessment type name based on department
     */
    function getAssessmentType($department) {
        $department = strtolower(trim($department ?? ''));
        $map = [
            'operations'                   => 'Operations Aptitude Test',
            'transportation'               => 'Driving & Safety Assessment',
            'fleet'                        => 'Driving & Safety Assessment',
            'warehouse'                    => 'Warehouse Operations Assessment',
            'customer_service'             => 'Customer Service Assessment',
            'customer service'             => 'Customer Service Assessment',
            'sales'                        => 'Sales Aptitude Assessment',
            'sales & business development' => 'Sales Aptitude Assessment',
            'finance'                      => 'Finance & Accounting Assessment',
            'finance & accounting'         => 'Finance & Accounting Assessment',
            'accounting'                   => 'Finance & Accounting Assessment',
            'procurement'                  => 'Procurement Assessment',
            'compliance'                   => 'Compliance & Legal Assessment',
            'compliance / legal & risk'    => 'Compliance & Legal Assessment',
            'legal'                        => 'Compliance & Legal Assessment',
            'it'                           => 'IT Technical Assessment',
            'safety_security'              => 'Safety & Security Assessment',
            'safety & security'            => 'Safety & Security Assessment',
            'safety'                       => 'Safety & Security Assessment',
            'security'                     => 'Safety & Security Assessment',
        ];
        return $map[$department] ?? 'General Aptitude Assessment';
    }
}