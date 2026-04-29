<?php
// config/PageList.php
//
// Maps access_control column names → human-readable labels, grouped by section.
// Every key here MUST exist as a column in the access_control table.
// "suppliers" and "employees" have been removed — they do not exist in farm_system.sql.

$permission_map = [
    "DASHBOARD" => [
        "dashboard"     => "Dashboard Main",
        "animal_record" => "Animal Record",
        "farm_roles"    => "Farm Roles",       // 🔒 disabled for New User / Farm Employee
        "employee_list" => "Employee List",    // 🔒 disabled for New User / Farm Employee
        "animal_type"   => "Animal Type",      // 🔒 disabled for New User / Farm Employee
        "location"      => "Location",         // 🔒 disabled for New User / Farm Employee
        "building"      => "Building",         // 🔒 disabled for New User / Farm Employee
        "pen"           => "Pen",              // 🔒 disabled for New User / Farm Employee
        "breed"         => "Breed",            // 🔒 disabled for New User / Farm Employee
        "veterinary"    => "Veterinary",       // 🔒 disabled for New User / Farm Employee
        "diseases"      => "Diseases",         // 🔒 disabled for New User / Farm Employee
        "buyer"         => "Buyer",            // 🔒 disabled for New User / Farm Employee
        "suppliers"     => "Suppliers",
    ],
    "COSTING" => [
        "costing"              => "Costing Module Access",
        "animal_cost"          => "Animal Cost",
        "feed_consumption"     => "Feed Consumption",
        "medication_treatment" => "Medication & Treatment",
        "vaccinations"         => "Vaccinations",
        "vitamins_supplements" => "Vitamins & Supplements",
        "veterinary_checkups"  => "Veterinary Check-ups",
    ],
    "FARM" => [
        "farm"              => "Farm Module Access",
        "animal_class"      => "Animal Class",
        "edit_bio_info"     => "Edit Bio Info",
        "event_scheduler"   => "Event Scheduler",
        "animal_transfer"   => "Animal Transfer",
        "sow_status"        => "Sow Status",
        "fcr_management"    => "FCR Management",
        "animal_weights"    => "Animal Weights",
        "animal_operations" => "Animal Operations",
        "sow_cards"         => "Sow Cards",
        "birth_certificate" => "Birth Certificate",
        "cost_transfer"     => "Cost Transfer",
        "concerns"     => "Concerns",
        "file_concerns"     => "File Concerns",
        "animal_misc_fees"  => "Miscellaneous Fees",
    ],
    "ANALYTICS" => [
        "analytics_dashboard"              => "Analytics Dashboard",
        "animals_livestock_analytics"      => "Animals/Livestock",
        "medicine_analytics"               => "Medicine",
        "vitamins_supplements_analytics"   => "Vitamins & Supplements",
        "vaccines_analytics"               => "Vaccines",
        "feeds_feeding_analytics"          => "Feeds & Feeding",
        "housing_facilities_analytics"     => "Housing & Facilities",
        "farm_equipment_tools_analytics"   => "Farm Equipment & Tools",
        "sanitation_waste_analytics"       => "Sanitation & Waste",
        "breeding_reproduction_analytics"  => "Breeding & Reproduction",
        "administration_records_analytics" => "Admin & Records",
        "maintenance_parts_analytics"      => "Maintenance Parts",
        "utilities_consumables_analytics"  => "Utilities & Consumables",
        "others_analytics"                 => "Others",
    ],
    "REPORTS" => [
        "reports"                                 => "Reports Module Access",
        "animal_report"                           => "Animal Report",
        "active_users_report"                     => "Active Users",
        "medicine_report"                         => "Medicine",
        "feeds_feeding_supplies_report"           => "Feeds & Feeding Supplies",
        "housing_facilities_report"               => "Housing & Facilities",
        "farm_equipment_tools_report"             => "Farm Equipment & Tools",
        "sanitation_waste_management_report"      => "Sanitation & Waste",
        "breeding_reproduction_report"            => "Breeding & Reproduction",
        "administration_records_report"           => "Admin & Records",
        "maintenance_parts_report"                => "Maintenance & Parts",
        "utilities_consumables_report"            => "Utilities & Consumables",
        "vitamins_supplements_report"             => "Vitamins & Supplements",
        "vaccine_report"                          => "Vaccine",
        "others_report"                           => "Others",
        "audit_log_report"                        => "Audit Log",
        "medication_report"                       => "Medication",
        "vaccination_report"                      => "Vaccination",
        "vitamins_supplements_transaction_report" => "Vitamins Trans.",
        "feeding_transaction_report"              => "Feeding Trans.",
        "animal_sales_report"                     => "Animal Sales",
    ],
    "TRANSACTIONS" => [
        "transactions"          => "Transactions Module Access",
        "individual_operations" => "Individual Operations",
        "feeding"               => "Feeding",
        "medication"            => "Medication",
        "vitamins_supplements_trans" => "Vitamins & Supplements",
        "check_ups"             => "Check Ups",
        "vaccination"           => "Vaccination",
        "purchases"             => "Purchases",
        "sell_animals"          => "Sell Animals",
        "batch_group_operations" => "Batch/Group Operations",
        "group_medication"      => "Group Medication",
        "group_vitamins"        => "Group Vitamins",
        "group_checkup"         => "Group Check-Up",
        "group_vaccination"     => "Group Vaccination",
        "group_sell_animals"    => "Group Sell Animals",
    ],
    "SYSTEM" => [
        "settings"        => "Settings",
        "manage_accounts" => "Manage Accounts",
        "audit_logs"      => "Audit Logs",
    ],
];

// Permissions that are locked (disabled) for USER_TYPE 1 (New User) and 2 (Farm Employee).
// This list is read by manage_access.php to disable checkboxes on page load
// and by the JS applyRolePreset() to disable them when the role selector changes.
$restricted_for_basic_roles = [
    'farm_roles',
    'employee_list',
    'animal_type',
    'location',
    'building',
    'pen',
    'breed',
    'veterinary',
    'diseases',
    'buyer',
    'suppliers',
        "reports",
        "animal_report",
        "active_users_report",
        "medicine_report",
        "feeds_feeding_supplies_report",
        "housing_facilities_report",
        "farm_equipment_tools_report",
        "sanitation_waste_management_report",
        "breeding_reproduction_report",
        "administration_records_report",
        "maintenance_parts_report",
        "utilities_consumables_report",
        "vitamins_supplements_report",
        "vaccine_report",
        "others_report",
        "audit_log_report",
        "medication_report",
        "vaccination_report",
        "vitamins_supplements_transaction_report",
        "feeding_transaction_report",
        "animal_sales_report",
        "analytics_dashboard"          ,
        "animals_livestock_analytics",
        "medicine_analytics",
        "vitamins_supplements_analytics"  ,
        "vaccines_analytics",
        "feeds_feeding_analytics"  ,
        "housing_facilities_analytics"  ,
        "farm_equipment_tools_analytics"  ,
        "sanitation_waste_analytics"  ,
        "breeding_reproduction_analytics"  ,
        "administration_records_analytics",
        "maintenance_parts_analytics",
        "utilities_consumables_analytics"  ,
        "others_analytics",
        "costing"             ,
        "animal_cost"    ,
        "feed_consumption" ,
        "medication_treatment",
        "vaccinations"         ,
        "vitamins_supplements" ,
        "veterinary_checkups"  ,
        "settings",
        "manage_accounts",
        "audit_logs",
];

// Permissions locked for everyone EXCEPT USER_TYPE 4 (Super Admin).
// The SYSTEM section (settings, manage_accounts, audit_logs) is super admin only.
$restricted_for_non_superadmin = [
    'settings',
    'manage_accounts',
    'audit_logs',
];