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

        // System (Implied/Required for UI)
        'dashboard.view' => 'View Dashboard',
        'settings.manage' => 'Manage Settings',
        'reports.export' => 'Export Reports',
        'analytics.view' => 'View Analytics',
        'analytics.train' => 'Train Analytics',
        'users.manage' => 'Manage Users',
    ],
    'role_permissions' => [
        'SuperAdmin' => ['*'],
        
        'Admin / Transport Officer' => [
            // Module 1: All permissions per Granular
            'module1.read', 'module1.write', 'module1.delete', 'module1.link_vehicle', 'module1.route_manage',
            
            // Module 2: All permissions per Granular (apply, endorse, approve, history)
            'module2.read', 'module2.apply', 'module2.endorse', 'module2.approve', 'module2.history',
            
            // Module 3: read, analytics (Granular does NOT give issue or settle to Admin)
            'module3.read', 'module3.analytics',
            
            // Module 4: schedule, certify (Granular does NOT give inspect to Admin)
            'module4.read', 'module4.schedule', 'module4.certify',
            
            // Module 5: manage_terminal (Granular does NOT give assign_vehicle or parking_fees to Admin)
            'module5.read', 'module5.manage_terminal',
            
            // System
            'dashboard.view', 'settings.manage', 'reports.export', 'analytics.view', 'analytics.train'
        ],

        'Franchise Officer' => [
            'module1.read',
            // Module 2: endorse, history (Granular: apply is Encoder/Admin, approve is Admin)
            // Summary says "Module 2 (full)", but Granular is specific.
            // Keeping strict to Granular + read.
            'module2.read', 'module2.endorse', 'module2.history',
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
            'module1.read',
            'module3.read', 'module3.settle',
            'module5.read', 'module5.parking_fees',
            'dashboard.view'
        ],

        'Terminal Manager' => [
            'module1.read',
            'module5.read', 'module5.manage_terminal', 'module5.assign_vehicle',
            'dashboard.view'
        ],
    ]
];
