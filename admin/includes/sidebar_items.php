<?php
$sidebarItems = [
  [
    'id' => 'dashboard',
    'label' => 'Dashboard',
    'icon' => 'gauge',
    'path' => '/dashboard',
  ],
  [
    'id' => 'module1',
    'label' => 'PUV Database',
    'icon' => 'bus',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Vehicle & Ownership Registry', 'path' => '/module1/submodule1'],
      ['id' => 'submodule2', 'label' => 'Operator & Franchise Validation', 'path' => '/module1/submodule2'],
      ['id' => 'submodule3', 'label' => 'Route & Terminal Assignment', 'path' => '/module1/submodule3'],
    ],
  ],
  [
    'id' => 'module2',
    'label' => 'Franchise Management',
    'icon' => 'shield-check',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module2/overview'],
      ['id' => 'submodule1', 'label' => 'Franchise Application & Cooperative', 'path' => '/module2/submodule1'],
      ['id' => 'submodule2', 'label' => 'Validation, Endorsement & Compliance', 'path' => '/module2/submodule2'],
      ['id' => 'submodule3', 'label' => 'Renewals, Monitoring & Reporting', 'path' => '/module2/submodule3'],
    ],
  ],
  [
    'id' => 'module3',
    'label' => 'Traffic Violation & Ticketing',
    'icon' => 'ticket',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module3/overview'],
      ['id' => 'submodule1', 'label' => 'Violation Logging & Ticket Processing', 'path' => '/module3/submodule1'],
      ['id' => 'submodule2', 'label' => 'Validation, Payment & Compliance', 'path' => '/module3/submodule2'],
      ['id' => 'submodule3', 'label' => 'Analytics, Reporting & Integration', 'path' => '/module3/submodule3'],
    ],
  ],
  [
    'id' => 'module4',
    'label' => 'Vehicle Inspection & Registration',
    'icon' => 'clipboard-check',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module4/overview'],
      ['id' => 'submodule1', 'label' => 'Vehicle Verification & Scheduling', 'path' => '/module4/submodule1'],
      ['id' => 'submodule2', 'label' => 'Inspection Execution & Certification', 'path' => '/module4/submodule2'],
      ['id' => 'submodule3', 'label' => 'Route Validation & Compliance Reporting', 'path' => '/module4/submodule3'],
    ],
  ],
  [
    'id' => 'module5',
    'label' => 'Parking & Terminal Management',
    'icon' => 'map-pin',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module5/overview'],
      ['id' => 'submodule1', 'label' => 'Terminal Management', 'path' => '/module5/submodule1'],
      ['id' => 'submodule2', 'label' => 'Parking Area Management', 'path' => '/module5/submodule2'],
      ['id' => 'submodule3', 'label' => 'Parking Fees, Enforcement & Analytics', 'path' => '/module5/submodule3'],
    ],
  ],
  [
    'id' => 'complaints',
    'label' => 'Citizen Complaints',
    'icon' => 'message-square',
    'path' => '/complaints/list',
  ],
  [
    'id' => 'settings',
    'label' => 'Settings',
    'icon' => 'settings',
    'subItems' => [
      ['id' => 'general-settings', 'label' => 'General', 'path' => '/settings/general'],
      ['id' => 'security-settings', 'label' => 'Security', 'path' => '/settings/security'],
    ],
  ],
];