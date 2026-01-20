<?php

return [
    'roles' => [
        'SuperAdmin' => 'Full access to everything',
        'Admin / Transport Officer' => 'Full access to all modules (except user management)',
        'Franchise Officer' => 'Handles franchise application & endorsement',
        'Encoder' => 'Data entry only',
        'Inspector' => 'Handles inspection scheduling & execution',
        'Traffic Enforcer' => 'Issues tickets',
        'Treasurer / Cashier' => 'Payment & settlement',
        'Terminal Manager' => 'Handles terminals & parking',
        // Keeping Viewer/Commuter as they seem to be system defaults or required by other parts
        'Viewer' => 'Read-only access',
        'Commuter' => 'Citizen portal account',
    ],
    'permissions' => [
        // Module 1
        'module1.read' => 'Module 1 - Read Access',
        'module1.write' => 'Module 1 - Write Access',
        'module1.delete' => 'Module 1 - Delete Access',
        'module1.link_vehicle' => 'Module 1 - Link Vehicle',
        'module1.route_manage' => 'Module 1 - Route Manage',

        // Module 2
        'module2.read' => 'Module 2 - Read Access',
        'module2.apply' => 'Module 2 - Apply',
        'module2.endorse' => 'Module 2 - Endorse',
        'module2.approve' => 'Module 2 - Approve',
        'module2.history' => 'Module 2 - History',

        // Module 3
        'module3.issue' => 'Module 3 - Issue Ticket',
        'module3.read' => 'Module 3 - Read Access',
        'module3.settle' => 'Module 3 - Settle',
        'module3.analytics' => 'Module 3 - Analytics',

        // Module 4
        'module4.schedule' => 'Module 4 - Schedule',
        'module4.inspect' => 'Module 4 - Inspect',
        'module4.read' => 'Module 4 - Read Access',
        'module4.certify' => 'Module 4 - Certify',

        // Module 5
        'module5.manage_terminal' => 'Module 5 - Manage Terminal',
        'module5.assign_vehicle' => 'Module 5 - Assign Vehicle',
        'module5.parking_fees' => 'Module 5 - Parking Fees',
        'module5.read' => 'Module 5 - Read Access',

        // System
        'dashboard.view' => 'View Dashboard',
        'settings.manage' => 'Manage Settings',
        'reports.export' => 'Export Reports',
        'analytics.view' => 'View Analytics',
        'analytics.train' => 'Train Analytics',
        'users.manage' => 'Manage Users', // Implied for SuperAdmin
    ],
    'role_permissions' => [
        'SuperAdmin' => ['*'], // Special handling or list all
        'Admin / Transport Officer' => [
            'module1.read', 'module1.write', 'module1.delete', 'module1.link_vehicle', 'module1.route_manage',
            'module2.read', 'module2.apply', 'module2.endorse', 'module2.approve', 'module2.history',
            'module3.read', 'module3.read', 'module3.analytics', // Admin has read and analytics
            'module4.read', 'module4.schedule', 'module4.certify', // Admin has schedule, certify
            'module5.read', 'module5.manage_terminal',
            'dashboard.view', 'settings.manage', 'reports.export', 'analytics.view', 'analytics.train'
        ],
        'Franchise Officer' => [
            'module1.read',
            'module2.read', 'module2.apply', 'module2.endorse', 'module2.history',
            'dashboard.view', 'reports.export'
        ],
        'Encoder' => [
            'module1.read', 'module1.write', 'module1.link_vehicle',
            'module2.read', 'module2.apply',
            'dashboard.view'
        ],
        'Inspector' => [
            'module1.read',
            'module4.read', 'module4.schedule', 'module4.inspect', 'module4.certify',
            'dashboard.view'
        ],
        'Traffic Enforcer' => [
            'module1.read',
            'module3.read', 'module3.issue',
            'dashboard.view'
        ],
        'Treasurer / Cashier' => [
            'module1.read', // Not explicitly in list but implied by 'read' access usually? RBAC.md says "Access: Module 3 (payment), Module 5 (parking fees)". But later "Module 3 read: All roles".
            'module3.read', 'module3.settle',
            'module5.read', 'module5.parking_fees',
            'dashboard.view'
        ],
        'Terminal Manager' => [
            'module1.read',
            'module5.read', 'module5.manage_terminal', 'module5.assign_vehicle',
            'dashboard.view'
        ],
        'Viewer' => [
             'module1.read', 'module2.read', 'module3.read', 'module4.read', 'module5.read',
             'dashboard.view'
        ],
        'Commuter' => []
    ]
];
