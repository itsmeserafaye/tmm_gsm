<?php
$sidebarItems = [
  [
    'id' => 'dashboard',
    'label' => 'Dashboard',
    'icon' => 'gauge',
    'path' => '/dashboard',
    'anyPermissions' => ['dashboard.view'],
  ],
  [
    'id' => 'module1',
    'label' => 'PUV Database',
    'icon' => 'bus',
    'subItems' => [
      ['id' => 'submodule1', 'label' => 'Vehicle & Ownership Registry', 'path' => '/module1/submodule1', 'anyPermissions' => ['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']],
      ['id' => 'submodule2', 'label' => 'Operator & Franchise Validation', 'path' => '/module1/submodule2', 'anyPermissions' => ['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']],
      ['id' => 'submodule3', 'label' => 'Route & Terminal Assignment', 'path' => '/module1/submodule3', 'anyPermissions' => ['module1.view','module1.vehicles.write','module1.routes.write','module1.coops.write']],
    ],
  ],
  [
    'id' => 'module2',
    'label' => 'Franchise Management',
    'icon' => 'shield-check',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module2/overview', 'anyPermissions' => ['module2.view','module2.franchises.manage']],
      ['id' => 'submodule1', 'label' => 'Franchise Application & Cooperative', 'path' => '/module2/submodule1', 'anyPermissions' => ['module2.view','module2.franchises.manage']],
      ['id' => 'submodule2', 'label' => 'Validation, Endorsement & Compliance', 'path' => '/module2/submodule2', 'anyPermissions' => ['module2.view','module2.franchises.manage']],
      ['id' => 'submodule3', 'label' => 'Renewals, Monitoring & Reporting', 'path' => '/module2/submodule3', 'anyPermissions' => ['module2.view','module2.franchises.manage']],
    ],
  ],
  [
    'id' => 'module3',
    'label' => 'Traffic Violation & Ticketing',
    'icon' => 'ticket',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module3/overview', 'anyPermissions' => ['module3.view','tickets.issue','tickets.validate','tickets.settle']],
      ['id' => 'submodule1', 'label' => 'Violation Logging & Ticket Processing', 'path' => '/module3/submodule1', 'anyPermissions' => ['module3.view','tickets.issue']],
      ['id' => 'submodule2', 'label' => 'Validation, Payment & Compliance', 'path' => '/module3/submodule2', 'anyPermissions' => ['module3.view','tickets.validate','tickets.settle']],
      ['id' => 'submodule3', 'label' => 'Analytics, Reporting & Integration', 'path' => '/module3/submodule3', 'anyPermissions' => ['module3.view','analytics.view','reports.export']],
    ],
  ],
  [
    'id' => 'module4',
    'label' => 'Vehicle Inspection & Registration',
    'icon' => 'clipboard-check',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module4/overview', 'anyPermissions' => ['module4.view','module4.inspections.manage']],
      ['id' => 'submodule1', 'label' => 'Vehicle Verification & Scheduling', 'path' => '/module4/submodule1', 'anyPermissions' => ['module4.view','module4.inspections.manage']],
      ['id' => 'submodule2', 'label' => 'Inspection Execution & Certification', 'path' => '/module4/submodule2', 'anyPermissions' => ['module4.view','module4.inspections.manage']],
      ['id' => 'submodule3', 'label' => 'Route Validation & Compliance Reporting', 'path' => '/module4/submodule3', 'anyPermissions' => ['module4.view','module4.inspections.manage']],
    ],
  ],
  [
    'id' => 'module5',
    'label' => 'Parking & Terminal Management',
    'icon' => 'map-pin',
    'subItems' => [
      ['id' => 'overview', 'label' => 'Overview', 'path' => '/module5/overview', 'anyPermissions' => ['module5.view','parking.manage']],
      ['id' => 'submodule1', 'label' => 'Terminal Management', 'path' => '/module5/submodule1', 'anyPermissions' => ['module5.view','parking.manage']],
      ['id' => 'submodule2', 'label' => 'Parking Area Management', 'path' => '/module5/submodule2', 'anyPermissions' => ['module5.view','parking.manage']],
      ['id' => 'submodule3', 'label' => 'Parking Fees, Enforcement & Analytics', 'path' => '/module5/submodule3', 'anyPermissions' => ['module5.view','parking.manage']],
    ],
  ],
  [
    'id' => 'users',
    'label' => 'User Management',
    'icon' => 'users',
    'subItems' => [
      ['id' => 'accounts', 'label' => 'Accounts & Roles', 'path' => '/users/accounts', 'roles' => ['SuperAdmin']],
      ['id' => 'security', 'label' => 'Security Policy', 'path' => '/users/security', 'anyPermissions' => ['settings.manage']],
      ['id' => 'activity', 'label' => 'Activity Logs', 'path' => '/users/activity', 'roles' => ['SuperAdmin']],
    ],
  ],
  [
    'id' => 'settings',
    'label' => 'Settings',
    'icon' => 'settings',
    'subItems' => [
      ['id' => 'general-settings', 'label' => 'General', 'path' => '/settings/general', 'anyPermissions' => ['settings.manage']],
    ],
  ],
];
