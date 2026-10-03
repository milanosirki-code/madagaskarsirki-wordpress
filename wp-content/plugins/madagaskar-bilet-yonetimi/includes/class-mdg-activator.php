<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MDG_Activator {
    public static function maybe_upgrade() {
        $installed = (string) get_option( 'mdg_bilet_db_version', '' );
        if ( $installed !== (string) MDG_BILET_VERSION ) {
            self::create_tables();
            if ( class_exists( 'MDG_Venues' ) ) { MDG_Venues::repair_existing_district_names(); }
            update_option( 'mdg_bilet_db_version', MDG_BILET_VERSION, false );
        }
    }

    public static function activate() {
        self::create_tables();
        update_option( 'mdg_bilet_db_version', MDG_BILET_VERSION, false );
    }

    public static function deactivate() {
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( 'mdg_cleanup_expired_holds', array(), 'madagaskar' );
        }
    }

    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $venues = MDG_DB::table( 'venues' );
        $events = MDG_DB::table( 'events' );
        $sessions = MDG_DB::table( 'sessions' );
        $ticket_types = MDG_DB::table( 'ticket_types' );
        $holds = MDG_DB::table( 'holds' );
        $order_map = MDG_DB::table( 'order_map' );
        $notifications = MDG_DB::table( 'notifications' );
        $audit = MDG_DB::table( 'audit_log' );

        dbDelta( "CREATE TABLE {$venues} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            province_code VARCHAR(16) NOT NULL,
            province_name VARCHAR(100) NOT NULL,
            district VARCHAR(120) NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            maps_url TEXT NULL,
            location_qr_attachment_id BIGINT UNSIGNED NULL,
            location_qr_hash CHAR(64) NULL,
            qr_updated_at DATETIME NULL,
            contact_name VARCHAR(190) NULL,
            contact_phone VARCHAR(80) NULL,
            default_capacity INT UNSIGNED NOT NULL DEFAULT 500,
            default_duration SMALLINT UNSIGNED NOT NULL DEFAULT 60,
            notes LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY province_code (province_code),
            KEY district (district),
            KEY is_active (is_active)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_uuid CHAR(36) NOT NULL,
            public_slug VARCHAR(190) NULL,
            import_code VARCHAR(80) NULL,
            title VARCHAR(190) NOT NULL,
            venue_id BIGINT UNSIGNED NULL,
            province_code VARCHAR(16) NOT NULL,
            province_name VARCHAR(100) NOT NULL,
            district VARCHAR(120) NOT NULL,
            venue_name VARCHAR(190) NOT NULL,
            venue_address TEXT NOT NULL,
            venue_latitude DECIMAL(10,7) NULL,
            venue_longitude DECIMAL(10,7) NULL,
            venue_maps_url TEXT NULL,
            venue_qr_attachment_id BIGINT UNSIGNED NULL,
            venue_default_capacity INT UNSIGNED NULL,
            venue_default_duration SMALLINT UNSIGNED NULL,
            short_description TEXT NULL,
            long_description LONGTEXT NULL,
            hero_attachment_id BIGINT UNSIGNED NULL,
            gallery_attachment_ids LONGTEXT NULL,
            video_url TEXT NULL,
            age_info VARCHAR(190) NULL,
            show_duration SMALLINT UNSIGNED NULL,
            doors_open_before SMALLINT UNSIGNED NULL,
            seating_type VARCHAR(30) NOT NULL DEFAULT 'free',
            rules LONGTEXT NULL,
            organizer_name VARCHAR(190) NULL,
            faq_json LONGTEXT NULL,
            seo_title VARCHAR(190) NULL,
            seo_description VARCHAR(320) NULL,
            og_attachment_id BIGINT UNSIGNED NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            sale_start DATETIME NULL,
            sale_end DATETIME NULL,
            wp_page_id BIGINT UNSIGNED NULL,
            cancellation_reason TEXT NULL,
            postponement_note TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY public_uuid (public_uuid),
            UNIQUE KEY public_slug (public_slug),
            UNIQUE KEY import_code (import_code),
            KEY venue_id (venue_id),
            KEY status (status),
            KEY province_code (province_code)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$sessions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            capacity_total INT UNSIGNED NOT NULL DEFAULT 0,
            held_units INT UNSIGNED NOT NULL DEFAULT 0,
            sold_units INT UNSIGNED NOT NULL DEFAULT 0,
            sale_start DATETIME NULL,
            sale_end DATETIME NULL,
            wc_product_id BIGINT UNSIGNED NULL,
            tickera_event_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY start_at (start_at),
            KEY status (status),
            KEY wc_product_id (wc_product_id),
            KEY tickera_event_id (tickera_event_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$ticket_types} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL,
            label VARCHAR(190) NOT NULL,
            price DECIMAL(18,2) NOT NULL DEFAULT 0,
            capacity_units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            wc_variation_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_code (session_id, code),
            KEY session_id (session_id),
            KEY wc_variation_id (wc_variation_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$holds} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            cart_token VARCHAR(100) NULL,
            units INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'held',
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY session_status (session_id, status),
            KEY order_id (order_id),
            KEY expires_at (expires_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$order_map} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED NOT NULL,
            ticket_type_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            units_per_ticket SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            units_total INT UNSIGNED NOT NULL DEFAULT 1,
            line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
            order_status VARCHAR(32) NOT NULL DEFAULT '',
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_item_id (order_item_id),
            KEY order_id (order_id),
            KEY event_id (event_id),
            KEY session_id (session_id),
            KEY paid_at (paid_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$notifications} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NULL,
            session_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            channel VARCHAR(20) NOT NULL,
            template_key VARCHAR(100) NULL,
            recipient VARCHAR(190) NULL,
            payload LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY event_id (event_id),
            KEY order_id (order_id),
            KEY scheduled_at (scheduled_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            action_key VARCHAR(100) NOT NULL,
            object_type VARCHAR(60) NULL,
            object_id BIGINT UNSIGNED NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY action_key (action_key),
            KEY object_ref (object_type, object_id),
            KEY created_at (created_at)
        ) {$charset};" );
    }
}
