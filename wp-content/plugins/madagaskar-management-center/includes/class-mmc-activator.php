<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMC_Activator {
    public static function activate() {
        self::install_or_upgrade();
        self::install_roles();
        update_option( 'mmc_installed_at', current_time( 'mysql' ) );
        if ( false === get_option( 'mmc_nightly_report_email', false ) ) { add_option( 'mmc_nightly_report_email', 'milanosirki@gmail.com', '', false ); }
        if ( false === get_option( 'mmc_nightly_report_hour', false ) ) { add_option( 'mmc_nightly_report_hour', 2, '', false ); }
        if ( class_exists( 'MMC_Report_Service' ) ) { MMC_Report_Service::reschedule(); }
        if ( class_exists( 'MMC_Kommo_Service' ) ) { MMC_Kommo_Service::register_rewrite(); flush_rewrite_rules(); }
    }

    public static function deactivate() {
        // Veri korunur. Kalıcı silme yalnız uninstall işlemi ile yapılır.
        wp_clear_scheduled_hook( 'mmc_kommo_process_queue' );
        wp_clear_scheduled_hook( 'mmc_nightly_report_cron' );
        flush_rewrite_rules();
    }

    public static function install_or_upgrade() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $programs = $wpdb->prefix . 'mmc_programs';
        $tasks    = $wpdb->prefix . 'mmc_tasks';
        $logs     = $wpdb->prefix . 'mmc_logs';
        $metrics  = $wpdb->prefix . 'mmc_region_metrics';
        $schools  = $wpdb->prefix . 'mmc_schools';
        $targets  = $wpdb->prefix . 'mmc_program_target_districts';
        $imports  = $wpdb->prefix . 'mmc_imports';
        $venues   = $wpdb->prefix . 'mmc_venues';
        $program_venues = $wpdb->prefix . 'mmc_program_venues';
        $documents = $wpdb->prefix . 'mmc_documents';
        $finance = $wpdb->prefix . 'mmc_finance_entries';
        $events = $wpdb->prefix . 'mmc_events';
        $sessions = $wpdb->prefix . 'mmc_sessions';
        $ticket_types = $wpdb->prefix . 'mmc_ticket_types';
        $integration_status = $wpdb->prefix . 'mmc_integration_status';
        $channel_prices = $wpdb->prefix . 'mmc_channel_prices';
        $sales_mappings = $wpdb->prefix . 'mmc_sales_mappings';
        $sales_ledger = $wpdb->prefix . 'mmc_sales_ledger';
        $kommo_profiles = $wpdb->prefix . 'mmc_kommo_profiles';
        $kommo_queue = $wpdb->prefix . 'mmc_kommo_queue';
        $kommo_templates = $wpdb->prefix . 'mmc_kommo_templates';
        $marketing_items = $wpdb->prefix . 'mmc_marketing_items';
        $meta_plans = $wpdb->prefix . 'mmc_meta_plans';
        $target_schools = $wpdb->prefix . 'mmc_program_target_schools';
        $field_visits = $wpdb->prefix . 'mmc_field_visits';
        $field_routes = $wpdb->prefix . 'mmc_field_routes';
        $field_tokens = $wpdb->prefix . 'mmc_field_tokens';
        $operation_plans = $wpdb->prefix . 'mmc_operation_plans';
        $resources = $wpdb->prefix . 'mmc_resources';
        $program_resources = $wpdb->prefix . 'mmc_program_resources';
        $operation_checklist = $wpdb->prefix . 'mmc_operation_checklist';
        $operation_schedule = $wpdb->prefix . 'mmc_operation_schedule';
        $invoices = $wpdb->prefix . 'mmc_invoices';
        $deposit_refunds = $wpdb->prefix . 'mmc_deposit_refunds';
        $financial_closures = $wpdb->prefix . 'mmc_financial_closures';
        $report_runs = $wpdb->prefix . 'mmc_report_runs';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_programs = "CREATE TABLE $programs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_code varchar(64) NOT NULL,
            brand varchar(64) NOT NULL DEFAULT 'Madagaskar Sirki',
            province_name varchar(120) NOT NULL,
            district_name varchar(120) NOT NULL DEFAULT '',
            plan_year smallint(4) unsigned NOT NULL,
            planned_date date DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'preparation',
            owner_user_id bigint(20) unsigned DEFAULT NULL,
            notes longtext DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY program_code (program_code),
            KEY status (status),
            KEY province_name (province_name),
            KEY district_name (district_name),
            KEY planned_date (planned_date)
        ) $charset_collate;";

        $sql_tasks = "CREATE TABLE $tasks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            module varchar(50) NOT NULL DEFAULT 'system',
            title varchar(255) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'open',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            due_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY program_id (program_id),
            KEY module (module),
            KEY status (status),
            KEY due_at (due_at)
        ) $charset_collate;";

        $sql_logs = "CREATE TABLE $logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned DEFAULT NULL,
            action varchar(80) NOT NULL,
            entity_type varchar(80) NOT NULL DEFAULT 'program',
            entity_id bigint(20) unsigned DEFAULT NULL,
            old_value longtext DEFAULT NULL,
            new_value longtext DEFAULT NULL,
            note text DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY program_id (program_id),
            KEY entity_type (entity_type),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_metrics = "CREATE TABLE $metrics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            province_name varchar(120) NOT NULL,
            district_name varchar(120) NOT NULL DEFAULT '',
            metric_key varchar(80) NOT NULL,
            metric_value decimal(20,2) NOT NULL DEFAULT 0,
            unit varchar(30) NOT NULL DEFAULT 'adet',
            data_year varchar(20) NOT NULL,
            source_org varchar(80) NOT NULL,
            source_name varchar(255) NOT NULL DEFAULT '',
            source_url text DEFAULT NULL,
            data_level varchar(20) NOT NULL DEFAULT 'district',
            verified_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY area_metric (province_name,district_name,metric_key),
            KEY metric_key (metric_key),
            KEY data_year (data_year),
            KEY source_org (source_org)
        ) $charset_collate;";

        $sql_schools = "CREATE TABLE $schools (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            institution_code varchar(60) NOT NULL DEFAULT '',
            province_name varchar(120) NOT NULL,
            district_name varchar(120) NOT NULL,
            school_name varchar(255) NOT NULL,
            school_type varchar(120) NOT NULL DEFAULT '',
            education_level varchar(80) NOT NULL DEFAULT '',
            ownership varchar(40) NOT NULL DEFAULT '',
            address text DEFAULT NULL,
            latitude decimal(10,7) DEFAULT NULL,
            longitude decimal(10,7) DEFAULT NULL,
            student_count int(11) unsigned DEFAULT NULL,
            data_year varchar(20) NOT NULL DEFAULT '',
            source_org varchar(80) NOT NULL DEFAULT 'MEB',
            source_url text DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY institution_code (institution_code),
            KEY province_district (province_name,district_name),
            KEY school_name (school_name(191)),
            KEY data_year (data_year)
        ) $charset_collate;";

        $sql_targets = "CREATE TABLE $targets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            province_name varchar(120) NOT NULL,
            district_name varchar(120) NOT NULL,
            is_primary tinyint(1) NOT NULL DEFAULT 0,
            is_selected tinyint(1) NOT NULL DEFAULT 1,
            population_snapshot bigint(20) unsigned DEFAULT NULL,
            population_year_snapshot smallint(5) unsigned DEFAULT NULL,
            population_source_snapshot varchar(190) NOT NULL DEFAULT '',
            population_snapshot_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY program_district (program_id,district_name),
            KEY province_name (province_name),
            KEY program_id (program_id)
        ) $charset_collate;";

        $sql_imports = "CREATE TABLE $imports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_type varchar(40) NOT NULL,
            filename varchar(255) NOT NULL DEFAULT '',
            source_org varchar(80) NOT NULL DEFAULT '',
            data_year varchar(20) NOT NULL DEFAULT '',
            rows_total int(11) unsigned NOT NULL DEFAULT 0,
            rows_success int(11) unsigned NOT NULL DEFAULT 0,
            rows_error int(11) unsigned NOT NULL DEFAULT 0,
            error_log longtext DEFAULT NULL,
            imported_by bigint(20) unsigned DEFAULT NULL,
            imported_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY import_type (import_type),
            KEY imported_at (imported_at)
        ) $charset_collate;";


        $sql_venues = "CREATE TABLE $venues (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            venue_name varchar(255) NOT NULL,
            province_name varchar(120) NOT NULL,
            district_name varchar(120) NOT NULL DEFAULT '',
            institution_name varchar(255) NOT NULL DEFAULT '',
            address text DEFAULT NULL,
            maps_url text DEFAULT NULL,
            default_capacity int(11) unsigned DEFAULT NULL,
            contact_name varchar(160) NOT NULL DEFAULT '',
            contact_phone varchar(80) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            operation_notes longtext DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY province_district (province_name,district_name),
            KEY venue_name (venue_name(191)),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_program_venues = "CREATE TABLE $program_venues (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            venue_id bigint(20) unsigned NOT NULL,
            venue_source varchar(20) NOT NULL DEFAULT 'legacy',
            priority_order int(11) unsigned NOT NULL DEFAULT 1,
            requested_date date DEFAULT NULL,
            alternative_dates text DEFAULT NULL,
            requested_sessions varchar(255) NOT NULL DEFAULT '',
            target_institution varchar(255) NOT NULL DEFAULT '',
            allocation_status varchar(30) NOT NULL DEFAULT 'draft',
            outgoing_reference varchar(120) NOT NULL DEFAULT '',
            response_reference varchar(120) NOT NULL DEFAULT '',
            request_sent_at datetime DEFAULT NULL,
            response_at datetime DEFAULT NULL,
            rental_amount decimal(18,2) NOT NULL DEFAULT 0,
            deposit_amount decimal(18,2) NOT NULL DEFAULT 0,
            payment_due_date date DEFAULT NULL,
            is_selected tinyint(1) NOT NULL DEFAULT 0,
            notes longtext DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_venue (program_id,venue_id),
            KEY program_id (program_id),
            KEY allocation_status (allocation_status),
            KEY is_selected (is_selected)
        ) $charset_collate;";

        $sql_documents = "CREATE TABLE $documents (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            entity_type varchar(80) NOT NULL DEFAULT 'program',
            entity_id bigint(20) unsigned DEFAULT NULL,
            doc_type varchar(80) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id),
            KEY doc_type (doc_type),
            KEY entity_lookup (entity_type,entity_id)
        ) $charset_collate;";

        $sql_finance = "CREATE TABLE $finance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            related_entity_type varchar(80) NOT NULL DEFAULT '',
            related_entity_id bigint(20) unsigned DEFAULT NULL,
            entry_class varchar(40) NOT NULL,
            category varchar(80) NOT NULL,
            title varchar(255) NOT NULL,
            amount decimal(18,2) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'planned',
            due_date date DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            reference_no varchar(120) NOT NULL DEFAULT '',
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id),
            KEY entry_class (entry_class),
            KEY category (category),
            KEY status (status),
            KEY related_entity (related_entity_type,related_entity_id)
        ) $charset_collate;";



        $sql_events = "CREATE TABLE $events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            program_venue_id bigint(20) unsigned DEFAULT NULL,
            event_code varchar(80) NOT NULL,
            event_title varchar(255) NOT NULL,
            event_date date DEFAULT NULL,
            seating_mode varchar(30) NOT NULL DEFAULT 'free',
            door_open_minutes int(11) unsigned NOT NULL DEFAULT 30,
            status varchar(30) NOT NULL DEFAULT 'draft',
            notes longtext DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_code (event_code),
            KEY program_id (program_id),
            KEY program_venue_id (program_venue_id),
            KEY event_date (event_date),
            KEY status (status)
        ) $charset_collate;";

        $sql_sessions = "CREATE TABLE $sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            session_time datetime NOT NULL,
            capacity int(11) unsigned NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'active',
            notes text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_session_time (event_id,session_time),
            KEY event_id (event_id),
            KEY session_time (session_time),
            KEY status (status)
        ) $charset_collate;";

        $sql_ticket_types = "CREATE TABLE $ticket_types (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            ticket_code varchar(60) NOT NULL,
            ticket_name varchar(160) NOT NULL,
            age_min int(11) unsigned DEFAULT NULL,
            age_max int(11) unsigned DEFAULT NULL,
            price decimal(18,2) NOT NULL DEFAULT 0,
            capacity_units int(11) unsigned NOT NULL DEFAULT 1,
            sort_order int(11) unsigned NOT NULL DEFAULT 10,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_ticket_code (event_id,ticket_code),
            KEY event_id (event_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_integration_status = "CREATE TABLE $integration_status (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            channel varchar(60) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            external_id varchar(190) NOT NULL DEFAULT '',
            external_url text DEFAULT NULL,
            notes text DEFAULT NULL,
            last_synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY event_channel (event_id,channel),
            KEY program_id (program_id),
            KEY event_id (event_id),
            KEY channel (channel),
            KEY status (status)
        ) $charset_collate;";



        $sql_channel_prices = "CREATE TABLE $channel_prices (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            ticket_type_id bigint(20) unsigned NOT NULL,
            channel varchar(60) NOT NULL,
            price decimal(18,2) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_channel (ticket_type_id,channel),
            KEY event_id (event_id),
            KEY channel (channel)
        ) $charset_collate;";


        $sql_sales_mappings = "CREATE TABLE $sales_mappings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            ticket_type_id bigint(20) unsigned NOT NULL,
            wc_product_id bigint(20) unsigned DEFAULT NULL,
            wc_variation_id bigint(20) unsigned DEFAULT NULL,
            tickera_event_id bigint(20) unsigned DEFAULT NULL,
            tickera_ticket_type_id bigint(20) unsigned DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_ticket (session_id,ticket_type_id),
            KEY program_id (program_id),
            KEY event_id (event_id),
            KEY wc_product_id (wc_product_id),
            KEY wc_variation_id (wc_variation_id),
            KEY tickera_event_id (tickera_event_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_sales_ledger = "CREATE TABLE $sales_ledger (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned NOT NULL,
            ticket_type_id bigint(20) unsigned NOT NULL,
            channel varchar(40) NOT NULL DEFAULT 'woocommerce',
            external_order_id bigint(20) unsigned NOT NULL,
            external_order_item_id bigint(20) unsigned NOT NULL,
            order_status varchar(40) NOT NULL DEFAULT '',
            payment_method varchar(100) NOT NULL DEFAULT '',
            payment_method_title varchar(190) NOT NULL DEFAULT '',
            quantity int(11) unsigned NOT NULL DEFAULT 0,
            refunded_quantity int(11) unsigned NOT NULL DEFAULT 0,
            net_quantity int(11) unsigned NOT NULL DEFAULT 0,
            capacity_units int(11) unsigned NOT NULL DEFAULT 0,
            gross_amount decimal(18,2) NOT NULL DEFAULT 0,
            refunded_amount decimal(18,2) NOT NULL DEFAULT 0,
            net_amount decimal(18,2) NOT NULL DEFAULT 0,
            paid_at datetime DEFAULT NULL,
            last_synced_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY channel_order_item (channel,external_order_item_id),
            KEY program_id (program_id),
            KEY event_id (event_id),
            KEY session_id (session_id),
            KEY external_order_id (external_order_id),
            KEY order_status (order_status),
            KEY paid_at (paid_at)
        ) $charset_collate;";



        $sql_kommo_profiles = "CREATE TABLE $kommo_profiles (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned DEFAULT NULL,
            source_token varchar(80) NOT NULL,
            source_url text DEFAULT NULL,
            source_hash char(64) NOT NULL DEFAULT '',
            ai_synced_hash char(64) NOT NULL DEFAULT '',
            search_keywords longtext DEFAULT NULL,
            kommo_lead_id varchar(80) NOT NULL DEFAULT '',
            crm_status varchar(30) NOT NULL DEFAULT 'not_synced',
            ai_source_id varchar(80) NOT NULL DEFAULT '',
            ai_source_status varchar(30) NOT NULL DEFAULT 'not_synced',
            last_synced_at datetime DEFAULT NULL,
            last_error text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_id (program_id),
            UNIQUE KEY source_token (source_token),
            KEY event_id (event_id),
            KEY crm_status (crm_status),
            KEY ai_source_status (ai_source_status)
        ) $charset_collate;";

        $sql_kommo_queue = "CREATE TABLE $kommo_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned DEFAULT NULL,
            job_type varchar(60) NOT NULL,
            payload longtext DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'queued',
            attempts int(11) unsigned NOT NULL DEFAULT 0,
            available_at datetime DEFAULT NULL,
            processed_at datetime DEFAULT NULL,
            last_error text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id),
            KEY event_id (event_id),
            KEY job_status (job_type,status),
            KEY available_at (available_at)
        ) $charset_collate;";

        $sql_kommo_templates = "CREATE TABLE $kommo_templates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned DEFAULT NULL,
            template_key varchar(120) NOT NULL,
            external_name varchar(190) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'expected',
            notes text DEFAULT NULL,
            last_checked_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_template (program_id,template_key),
            KEY event_id (event_id),
            KEY status (status)
        ) $charset_collate;";



        $sql_marketing_items = "CREATE TABLE $marketing_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned DEFAULT NULL,
            item_type varchar(60) NOT NULL,
            channel varchar(40) NOT NULL DEFAULT 'creative',
            version_no int(11) unsigned NOT NULL DEFAULT 1,
            title varchar(255) NOT NULL DEFAULT '',
            body longtext DEFAULT NULL,
            brief longtext DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            source_hash char(64) NOT NULL DEFAULT '',
            scheduled_at datetime DEFAULT NULL,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            external_id varchar(190) NOT NULL DEFAULT '',
            external_url text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_type_version (program_id,item_type,version_no),
            KEY program_id (program_id),
            KEY event_id (event_id),
            KEY item_status (item_type,status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";

        $sql_meta_plans = "CREATE TABLE $meta_plans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned DEFAULT NULL,
            campaign_name varchar(255) NOT NULL DEFAULT '',
            objective varchar(60) NOT NULL DEFAULT 'sales',
            geo_summary text DEFAULT NULL,
            audience_notes longtext DEFAULT NULL,
            daily_budget decimal(18,2) NOT NULL DEFAULT 0,
            total_budget decimal(18,2) NOT NULL DEFAULT 0,
            start_at datetime DEFAULT NULL,
            end_at datetime DEFAULT NULL,
            utm_source varchar(80) NOT NULL DEFAULT 'meta',
            utm_medium varchar(80) NOT NULL DEFAULT 'paid_social',
            utm_campaign varchar(190) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'draft',
            source_hash char(64) NOT NULL DEFAULT '',
            external_campaign_id varchar(100) NOT NULL DEFAULT '',
            external_adset_id varchar(100) NOT NULL DEFAULT '',
            external_ad_id varchar(100) NOT NULL DEFAULT '',
            spend decimal(18,2) NOT NULL DEFAULT 0,
            purchases int(11) unsigned NOT NULL DEFAULT 0,
            revenue decimal(18,2) NOT NULL DEFAULT 0,
            cpa decimal(18,4) NOT NULL DEFAULT 0,
            roas decimal(18,4) NOT NULL DEFAULT 0,
            last_synced_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_id (program_id),
            KEY event_id (event_id),
            KEY status (status),
            KEY start_at (start_at)
        ) $charset_collate;";



        $sql_target_schools = "CREATE TABLE $target_schools (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            school_id bigint(20) unsigned NOT NULL,
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            assigned_name varchar(190) NOT NULL DEFAULT '',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            status varchar(30) NOT NULL DEFAULT 'planned',
            student_count_snapshot int(11) unsigned DEFAULT NULL,
            data_year_snapshot varchar(20) NOT NULL DEFAULT '',
            visit_count int(11) unsigned NOT NULL DEFAULT 0,
            last_visit_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_school (program_id,school_id),
            KEY program_id (program_id),
            KEY school_id (school_id),
            KEY assigned_user_id (assigned_user_id),
            KEY status (status)
        ) $charset_collate;";

        $sql_field_visits = "CREATE TABLE $field_visits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            target_school_id bigint(20) unsigned NOT NULL,
            school_id bigint(20) unsigned NOT NULL,
            visitor_user_id bigint(20) unsigned DEFAULT NULL,
            visitor_name varchar(190) NOT NULL DEFAULT '',
            visit_status varchar(30) NOT NULL DEFAULT 'visited',
            visited_at datetime NOT NULL,
            students_reached int(11) unsigned NOT NULL DEFAULT 0,
            materials_delivered int(11) unsigned NOT NULL DEFAULT 0,
            photo_attachment_id bigint(20) unsigned DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id),
            KEY target_school_id (target_school_id),
            KEY school_id (school_id),
            KEY visitor_user_id (visitor_user_id),
            KEY visit_status (visit_status),
            KEY visited_at (visited_at)
        ) $charset_collate;";

        $sql_field_routes = "CREATE TABLE $field_routes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            route_app varchar(60) NOT NULL DEFAULT 'custom',
            external_url text NOT NULL,
            district_name varchar(120) NOT NULL DEFAULT '',
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            assigned_name varchar(190) NOT NULL DEFAULT '',
            notes text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY program_id (program_id),
            KEY assigned_user_id (assigned_user_id),
            KEY district_name (district_name),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_field_tokens = "CREATE TABLE $field_tokens (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            token_hint varchar(16) NOT NULL DEFAULT '',
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            assigned_name varchar(190) NOT NULL DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            expires_at datetime DEFAULT NULL,
            last_used_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY program_id (program_id),
            KEY assigned_user_id (assigned_user_id),
            KEY is_active (is_active),
            KEY expires_at (expires_at)
        ) $charset_collate;";


        $sql_operation_plans = "CREATE TABLE $operation_plans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            operation_mode varchar(30) NOT NULL DEFAULT 'undecided',
            origin_city varchar(120) NOT NULL DEFAULT 'Ankara',
            next_destination varchar(190) NOT NULL DEFAULT '',
            departure_at datetime DEFAULT NULL,
            venue_entry_at datetime DEFAULT NULL,
            setup_start_at datetime DEFAULT NULL,
            rehearsal_at datetime DEFAULT NULL,
            doors_open_at datetime DEFAULT NULL,
            teardown_end_at datetime DEFAULT NULL,
            return_at datetime DEFAULT NULL,
            accommodation_required tinyint(1) NOT NULL DEFAULT 0,
            lodging_name varchar(255) NOT NULL DEFAULT '',
            lodging_address text DEFAULT NULL,
            lodging_rooms int(11) unsigned NOT NULL DEFAULT 0,
            lodging_cost decimal(18,2) NOT NULL DEFAULT 0,
            meal_plan longtext DEFAULT NULL,
            meal_cost decimal(18,2) NOT NULL DEFAULT 0,
            transport_cost decimal(18,2) NOT NULL DEFAULT 0,
            other_cost decimal(18,2) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'draft',
            notes longtext DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_id (program_id),
            KEY operation_mode (operation_mode),
            KEY status (status),
            KEY departure_at (departure_at)
        ) $charset_collate;";

        $sql_resources = "CREATE TABLE $resources (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            resource_type varchar(30) NOT NULL,
            resource_name varchar(255) NOT NULL,
            subtype varchar(160) NOT NULL DEFAULT '',
            identifier varchar(120) NOT NULL DEFAULT '',
            country varchar(120) NOT NULL DEFAULT '',
            notes longtext DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY resource_type (resource_type),
            KEY resource_name (resource_name(191)),
            KEY identifier (identifier),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql_program_resources = "CREATE TABLE $program_resources (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            resource_id bigint(20) unsigned NOT NULL,
            resource_type varchar(30) NOT NULL,
            resource_name varchar(255) NOT NULL,
            role_name varchar(190) NOT NULL DEFAULT '',
            quantity int(11) unsigned NOT NULL DEFAULT 1,
            status varchar(30) NOT NULL DEFAULT 'planned',
            check_in_at datetime DEFAULT NULL,
            check_out_at datetime DEFAULT NULL,
            notes longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_resource (program_id,resource_id),
            KEY program_id (program_id),
            KEY resource_type (resource_type),
            KEY status (status)
        ) $charset_collate;";

        $sql_operation_checklist = "CREATE TABLE $operation_checklist (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            phase varchar(40) NOT NULL,
            item_key varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            is_required tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) unsigned NOT NULL DEFAULT 10,
            status varchar(30) NOT NULL DEFAULT 'pending',
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            assigned_name varchar(190) NOT NULL DEFAULT '',
            due_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            evidence_attachment_id bigint(20) unsigned DEFAULT NULL,
            notes longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_item (program_id,item_key),
            KEY program_phase (program_id,phase),
            KEY status (status),
            KEY due_at (due_at)
        ) $charset_collate;";

        $sql_operation_schedule = "CREATE TABLE $operation_schedule (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            session_id bigint(20) unsigned DEFAULT NULL,
            source_key varchar(120) NOT NULL,
            item_type varchar(50) NOT NULL DEFAULT 'other',
            title varchar(255) NOT NULL,
            start_at datetime DEFAULT NULL,
            end_at datetime DEFAULT NULL,
            assigned_user_id bigint(20) unsigned DEFAULT NULL,
            assigned_name varchar(190) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'planned',
            sort_order int(11) unsigned NOT NULL DEFAULT 10,
            notes longtext DEFAULT NULL,
            is_system tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_source (program_id,source_key),
            KEY program_id (program_id),
            KEY session_id (session_id),
            KEY start_at (start_at),
            KEY status (status)
        ) $charset_collate;";

        $sql_invoices = "CREATE TABLE $invoices (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            source_type varchar(50) NOT NULL DEFAULT 'manual',
            source_id bigint(20) unsigned DEFAULT NULL,
            direction varchar(20) NOT NULL DEFAULT 'income',
            invoice_type varchar(30) NOT NULL DEFAULT 'e_archive',
            customer_name varchar(255) NOT NULL DEFAULT '',
            customer_email varchar(190) NOT NULL DEFAULT '',
            amount decimal(18,2) NOT NULL DEFAULT 0,
            vat_amount decimal(18,2) NOT NULL DEFAULT 0,
            invoice_no varchar(120) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pending',
            invoice_date date DEFAULT NULL,
            external_url text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_source (program_id,source_type,source_id),
            KEY program_id (program_id),
            KEY status (status),
            KEY direction (direction)
        ) $charset_collate;";

        $sql_deposit_refunds = "CREATE TABLE $deposit_refunds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            program_venue_id bigint(20) unsigned DEFAULT NULL,
            finance_entry_id bigint(20) unsigned NOT NULL,
            deposit_amount decimal(18,2) NOT NULL DEFAULT 0,
            requested_amount decimal(18,2) NOT NULL DEFAULT 0,
            refunded_amount decimal(18,2) NOT NULL DEFAULT 0,
            deduction_amount decimal(18,2) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'draft',
            request_document_id bigint(20) unsigned DEFAULT NULL,
            request_date date DEFAULT NULL,
            outgoing_reference varchar(120) NOT NULL DEFAULT '',
            refund_reference varchar(120) NOT NULL DEFAULT '',
            refunded_at datetime DEFAULT NULL,
            deduction_reason text DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY finance_entry_id (finance_entry_id),
            KEY program_id (program_id),
            KEY status (status),
            KEY program_venue_id (program_venue_id)
        ) $charset_collate;";

        $sql_financial_closures = "CREATE TABLE $financial_closures (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            program_id bigint(20) unsigned NOT NULL,
            manual_paid_audience int(11) unsigned NOT NULL DEFAULT 0,
            free_audience int(11) unsigned NOT NULL DEFAULT 0,
            total_revenue decimal(18,2) NOT NULL DEFAULT 0,
            total_expense decimal(18,2) NOT NULL DEFAULT 0,
            net_profit decimal(18,2) NOT NULL DEFAULT 0,
            profit_margin decimal(9,2) NOT NULL DEFAULT 0,
            total_audience int(11) unsigned NOT NULL DEFAULT 0,
            revenue_per_person decimal(18,2) NOT NULL DEFAULT 0,
            expense_per_person decimal(18,2) NOT NULL DEFAULT 0,
            outstanding_deposit decimal(18,2) NOT NULL DEFAULT 0,
            pending_invoice_count int(11) unsigned NOT NULL DEFAULT 0,
            close_status varchar(30) NOT NULL DEFAULT 'open',
            snapshot_json longtext DEFAULT NULL,
            notes longtext DEFAULT NULL,
            closed_by bigint(20) unsigned DEFAULT NULL,
            closed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY program_id (program_id),
            KEY close_status (close_status)
        ) $charset_collate;";

        $sql_report_runs = "CREATE TABLE $report_runs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_date date NOT NULL,
            generated_at datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            recipient varchar(190) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'generated',
            trigger_type varchar(30) NOT NULL DEFAULT 'cron',
            body_html longtext DEFAULT NULL,
            snapshot_json longtext DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY report_date (report_date),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";

        dbDelta( $sql_programs );
        dbDelta( $sql_tasks );
        dbDelta( $sql_logs );
        dbDelta( $sql_metrics );
        dbDelta( $sql_schools );
        dbDelta( $sql_targets );
        dbDelta( $sql_imports );
        dbDelta( $sql_venues );
        dbDelta( $sql_program_venues );
        dbDelta( $sql_documents );
        dbDelta( $sql_finance );
        dbDelta( $sql_events );
        dbDelta( $sql_sessions );
        dbDelta( $sql_ticket_types );
        dbDelta( $sql_integration_status );
        dbDelta( $sql_channel_prices );
        dbDelta( $sql_sales_mappings );
        dbDelta( $sql_sales_ledger );
        dbDelta( $sql_kommo_profiles );
        dbDelta( $sql_kommo_queue );
        dbDelta( $sql_kommo_templates );
        dbDelta( $sql_marketing_items );
        dbDelta( $sql_meta_plans );
        dbDelta( $sql_target_schools );
        dbDelta( $sql_field_visits );
        dbDelta( $sql_field_routes );
        dbDelta( $sql_field_tokens );
        dbDelta( $sql_operation_plans );
        dbDelta( $sql_resources );
        dbDelta( $sql_program_resources );
        dbDelta( $sql_operation_checklist );
        dbDelta( $sql_operation_schedule );
        dbDelta( $sql_invoices );
        dbDelta( $sql_deposit_refunds );
        dbDelta( $sql_financial_closures );
        dbDelta( $sql_report_runs );

        update_option( 'mmc_db_version', MMC_DB_VERSION );
    }

    public static function install_roles() {
        $caps_all = array(
            'read'                   => true,
            'mmc_view_dashboard'     => true,
            'mmc_view_programs'      => true,
            'mmc_manage_programs'    => true,
            'mmc_manage_tasks'       => true,
            'mmc_manage_region_data' => true,
            'mmc_manage_venues'      => true,
            'mmc_manage_events'      => true,
            'mmc_manage_sales'       => true,
            'mmc_manage_kommo'       => true,
            'mmc_manage_marketing'   => true,
            'mmc_manage_operations'  => true,
            'mmc_manage_field'       => true,
            'mmc_manage_finance'     => true,
            'mmc_manage_settings'    => true,
            'mmc_view_reports'       => true,
        );

        self::upsert_role( 'mmc_manager', 'Madagaskar Yönetici', $caps_all );
        self::upsert_role( 'mmc_operations', 'Madagaskar Operasyon', array(
            'read' => true,
            'mmc_view_dashboard' => true,
            'mmc_view_programs' => true,
            'mmc_manage_tasks' => true,
            'mmc_manage_operations' => true,
            'mmc_manage_venues' => true,
            'mmc_manage_events' => true,
            'mmc_manage_kommo' => true,
        ) );
        self::upsert_role( 'mmc_marketing', 'Madagaskar Pazarlama', array(
            'read' => true,
            'mmc_view_dashboard' => true,
            'mmc_view_programs' => true,
            'mmc_manage_marketing' => true,
            'mmc_manage_tasks' => true,
        ) );
        self::upsert_role( 'mmc_field', 'Madagaskar Saha', array(
            'read' => true,
            'mmc_view_dashboard' => true,
            'mmc_view_programs' => true,
            'mmc_manage_field' => true,
        ) );
        self::upsert_role( 'mmc_finance', 'Madagaskar Finans', array(
            'read' => true,
            'mmc_view_dashboard' => true,
            'mmc_view_programs' => true,
            'mmc_manage_finance' => true,
            'mmc_view_reports' => true,
        ) );
        self::upsert_role( 'mmc_viewer', 'Madagaskar Görüntüleyici', array(
            'read' => true,
            'mmc_view_dashboard' => true,
            'mmc_view_programs' => true,
            'mmc_view_reports' => true,
        ) );

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( array_keys( $caps_all ) as $cap ) {
                $administrator->add_cap( $cap );
            }
        }
    }

    private static function upsert_role( $slug, $name, $caps ) {
        $role = get_role( $slug );
        if ( ! $role ) {
            add_role( $slug, $name, $caps );
            return;
        }
        foreach ( $caps as $cap => $grant ) {
            if ( $grant ) {
                $role->add_cap( $cap );
            }
        }
    }
}
