<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title' => 'FinanceBase',
    'title_prefix' => '',
    'title_postfix' => ' | FinanceBase',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    // 'favicon' => [
    //     'enabled' => true,
    //     'path' => 'img/dukabase-logo.png',
    // ],


    'use_ico_only' => false,
    'use_full_favicon' => true,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'logo' => '<b>FinanceBase</b>',
    'logo_img' => 'img/dukabase-logo.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'FinanceBase Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo' => [
        'enabled' => false,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'Auth Logo',
            'class' => '',
            'width' => 50,
            'height' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader' => [
        'enabled' => true,
        'mode' => 'cwrapper', // reduce overlap with navbar/sidebar
        'img' => [
            'path' => 'img/dukabase-loader.svg',
            'alt' => 'Loading DukaBase…',
            'effect' => null,
            'width' => 64,
            'height' => 64,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled' => false,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => null,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url' => true,
    'dashboard_url' => 'dashboard',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => 'register',
    'password_reset_url' => 'password/reset',
    'password_email_url' => 'password/email',
    'profile_url' => false,
    'disable_darkmode_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'menu' => [
        // Navbar items:
        [
            'type' => 'navbar-search',
            'text' => 'search',
            'topnav_right' => true,
        ],
        [
            'type' => 'fullscreen-widget',
            'topnav_right' => true,
        ],
        [
            'type' => 'navbar-item',
            'topnav_right' => true,
            'text' => '',
            'icon' => 'fas fa-moon',
            'url' => '#',
            'data' => [
                'role' => 'theme-toggle',
                'toggle' => 'tooltip',
                'placement' => 'bottom',
                'title' => 'Toggle dark/light mode',
            ],
            'classes' => 'px-3',
        ],
        [
            'type' => 'navbar-item',
            'topnav_right' => true,
            'text' => '', // no text
            'icon' => 'fas fa-network-wired',
            'url' => '/subshops/choose',
            'data' => [
                'toggle' => 'tooltip',
                'placement' => 'bottom',
                'title' => 'Switch branch',
            ],
            'classes' => 'px-3', // optional: tweak spacing
        ],

        


        // Sidebar items:
        // Search bar
        [
            'type' => 'sidebar-menu-search',
            'text' => 'Search',
        ],

        // Dashboard
        [
            'text' => 'Dashboard',
            'url' => '/dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'can' => 'view_dashboard',
        ],

        // Messages
        [
            'text' => 'Messages',
            'url' => '/messages',
            'icon' => 'fas fa-envelope',
            'label' => 'unread_messages_count',          // number ya orders pending
            'label_color' => 'success',
        ],
    // Branch Management (Branches) - Only for Super Admin
    [
        'text' => 'Finances Management',
        'icon' => 'fas fa-crown',
        'can' => 'Super Admin', // Only show for Super Admin role
        'submenu' => [
            [
                'text' => 'Main Finance',
                'url' => '/shopsmanagement',
            ],
            [
                'text' => 'Owners',
                'url' => '/owners',
            ],

            [
                'text' => 'SMS Management',
                'url' => '/sms-management',
            ],


            [
                'text' => 'Payments',
                'url' => '/payments',
            ],

            [
                'text' => 'Security',
                'url' => '/security',
            ],
            [
                'text' => 'Data',
                'url' => '/data',
            ],
            [
                'text' => 'Telescope',
                'url' => '/telescope',
            ],


        ],
    ],
        [
            'text' => 'Finance Branches',
            'url' => '/shop',
            'icon' => 'fas fa-network-wired',
            'can' => 'view_shop', // Only show for Super Admin role and owner

        ],


        //Accounting

         [
            'text' => 'Accounting',
            'icon' => 'fas fa-calculator',
            'submenu' => [
                [
                    'text' => 'Charts Of Account ',
                    'url' => 'accounting/charts_of_account/',
                ],
                [
                    'text' => 'Accounting Settings',
                    'url' => 'accounting/accounting_settings/',
                ],
         
                // [
                //     'text' => 'Loans',
                //     'url' => 'loans/loans',
                // ], 

                // [
                //     'text' => 'Loan Groups',
                //     'url' => '/loans/loans_group',
                // ], 
             
             
            ],
        ],

        //Loan management

         [
            'text' => 'Loan Management',
            'icon' => 'fas fa-university',
            'submenu' => [
                [
                    'text' => 'Loan Products',
                    'url' => '/loans/loans_products',
                ],
         
                [
                    'text' => 'Loans',
                    'url' => 'loans/loans',
                ], 

                [
                    'text' => 'Loan Groups',
                    'url' => '/loans/loans_group',
                ], 

                [
                    'text' => 'Loan Settings',
                    'url' => '/loans/loans_settings',
                ], 
             
             
            ],
        ],
    

        // Products & Inventory
        

        [
            'text' => 'Inventory',
            'icon' => 'fas fa-warehouse',
            'can' => 'view_inventory',
            'submenu' => [
                [
                    'text' => 'Items | Products',
                    'url' => '/admin/inventory/items',
                    'can' => 'view_items',
                ],
         
                [
                    'text' => 'Categories',
                    'url' => '/admin/inventory/categories',
                    'can' => 'view_categories',
                ], 

                [
                    'text' => 'Items Transfer',
                    'url' => '/admin/inventory/transfers',
                    'can' => 'view_items_transfers',
                ], 
             
             
            ],
        ],

        // Orders & Sales
      
        [
            'text' => 'Sales',
            'icon' => 'fas fa-dollar-sign',
            'can' => 'view_sales',
            'submenu' => [
                [
                    'text' => 'POS',
                    'url' => '/admin/sales/pos',
                    'can' => 'view_pos'
                ],
                [
                    'text' => 'Invoice History',
                    'url' => '/admin/sales/invoices',
                    'can'=> 'view_invoice_history',
                ],
                [
                    'text' => 'Sales Returns',
                    'url' => '/admin/sales/returns',
                    'can' => 'view_sales_returns',
                ],
                [
                    'text' => 'Sales Transactions',
                    'url' => '/admin/sales/transactions',
                    'can' => 'view_sales_transactions',
                ],
           
            ],
        ],

        // Purchases
        [
            'text' => 'Purchases',
            'icon' => 'fas fa-shopping-basket',
            'can' => 'view_purchases',
            'submenu' => [
                [
                    'text' => 'New Purchase',
                    'url' => '/admin/purchases/purchase',
                    'can' => 'view_new_purchases',
                ],
                [
                    'text' => 'Purchase History',
                    'url' => '/admin/purchases/purchase-orders',
                    'can' => 'view_purchase_history',
                ],
                  [
                    'text' => 'Purchase Returns',
                    'url' => '/admin/purchases/returns',
                    'can' => 'view_purchase_returns',
                ], 
                [
                    'text' => 'Purchases Transactions',
                    'url' => '/admin/purchases/transactions',
                    'can' => 'view_purchase_transactions',
                ],
           
             
            ],
        ],
        [
            'text' => 'Expenses',
            'url' => '/admin/finance/expenses',
            'icon' => 'fas fa-receipt',
            'can' => 'view_expenses',
            'label' => 'pending_expenses_count',
            'label_color' => 'warning',
        ],
        [
            'text' => 'Write Offs',
            'url' => '/admin/inventory/writeoffs',
            'icon' => 'fa fa-minus-circle',
            'can' => 'view_writeoffs',
            'label' => 'pending_writeoffs_count',
            'label_color' => 'warning',
       
        ],
        [
            'text' => 'Customers',
            'url' => '/admin/sales/customers',
            'icon' => 'fas fa-users',
            'can' => 'view_customers',
        ],
        [
            'text' => 'Suppliers',
            'url' => '/admin/inventory/suppliers',
            'icon' => 'fas fa-truck',
            'can' => 'view_suppliers',
        ],

        ['header' => 'Managements'],

        //banks
           [
            'text' => 'Banks',
            'url' => '/admin/finance/banks',
            'icon' => 'fas fa-university',
            'can' => 'view_banks'
        ],

        // Reports
        [
            'text' => 'Reports',
            'icon' => 'fas fa-chart-line',
            'can' => 'view_reports',
            'submenu' => [
                [
                    'text' => 'Inventory Report',
                    'url' => '/admin/reports/inventory',
                    'can' => 'view_inventory_report',
                ],
                [
                    'text' => 'Inventory Ledger',
                    'url' => '/admin/reports/inventory/ledger',
                    'can' => 'view_inventory_ledger',
                    
                ],
                [
                    'text' => 'Sales Report',
                    'url' => '/admin/reports/sales',
                    'can' => 'view_sales_report',
                ],
                [
                    'text' => 'Purchase Report',
                    'url' => '/admin/reports/purchases',
                    'can' => 'view_purchases_report',
                ],
                [
                    'text' => 'Profit & Loss Report',
                    'url' => '/admin/reports/profit-and-loss',
                    'can' => 'view_profit_and_loss_report',
                ],
         
            ],
        ],

        // Users & Roles
        [
            'text' => 'Users',
            'url' => '/admin/users',
            'icon' => 'fas fa-users',
            'can' => 'admin-or-owner', // Only show for Super Admin role and owner
        ],
        [
            'text' => 'Roles & Permissions',
            'url' => 'settings/roles-permissions',
            'icon' => 'fas fa-user-shield',
            'can' => 'admin-or-owner', // Only show for Super Admin role and owner
        ],


        // Account settings
        ['header' => 'Account Settings'],
        [
            'text' => 'Profile',
            'url' => '/settings/profile',
            'icon' => 'fas fa-user',
        ],
        [
            'text' => 'Change Password',
            'url' => 'settings/password',
            'icon' => 'fas fa-lock',
        ],
        [
            'text' => 'UI settings',
            'url' => 'settings/ui-settings',
            'icon' => 'fas fa-lock',
            'can' => 'admin-or-owner', // Only show for Super Admin role and owner

        ],



          // Printer settings
        [
            'text' => 'Printer Settings',
            'icon' => 'fas fa-print',
             'can' => 'view_printer_settings',
            'submenu' => [
                [
                    'text' => 'Configure Printer',
                    'url' => '/settings/printers',
                     'can' => 'view_configure_printer',
                ],
                [
                    'text' => 'Print Jobs',
                    'url' => '/settings/printers/jobs',
                     'can' => 'view_print_jobs',
                    
                ],
                
         
            ],
        ],


        // Labels / Important Info
        ['header' => 'Labels'],
        [
            'text' => 'Important',
            'icon_color' => 'red',
            'url' => '#',
        ],
        [
            'text' => 'Warning',
            'icon_color' => 'yellow',
            'url' => '#',
        ],
        [
            'text' => 'Information',
            'icon_color' => 'cyan',
            'url' => '#',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
        App\Menu\Filters\UnreadMessagesFilter::class,
        App\Menu\Filters\PendingExpenses::class,
        App\Menu\Filters\PendingWriteoffs::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/datatables/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/datatables/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/datatables/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/select2/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/select2/css/select2.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/chartjs/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/sweetalert2/sweetalert2.min.js',
                ],
            ],
        ],
        'Pace' => [
            'active' => false, // disable extra progress loader to avoid duplicates
            'files' => [
                [
                    'type' => 'css',
                    'asset' => true,
                    'location' => 'vendor/pace/css/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/pace/js/pace.min.js',
                ],
            ],
        ],
        // Custom small helper to auto-hide AdminLTE preloader using CSS fade-out
        'PreloaderHelper' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/preloader.js',
                ],
            ],
        ],
        'ThemeToggle' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'js/theme-toggle.js',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire' => false,

    
];
