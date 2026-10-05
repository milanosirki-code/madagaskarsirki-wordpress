<?php

/**
 * Madagaskar Çekiliş — Eksik Slotlar İçin Manuel Yeniden Seçim V2
 *
 * Amaç:
 * - Mevcut Madagaskar Çekiliş Sistemi v3.3.0 tablolarını kullanır.
 * - Bir asil/yedek "disqualified" olduğunda yalnız o pozisyon için 1 yeni kişi seçer.
 * - Geçersiz kişi ve daha önce seçilmiş comment_id'leri tekrar seçmez.
 * - Kampanyadaki one_user_one_win / one_user_one_entry kurallarına uyar.
 * - Eski sonucu ayrı audit tablosunda saklar.
 * - Mevcut Meta yorum çekme ve ilk çekiliş koduna dokunmaz.
 */

if ( ! defined( 'MCKR_VERSION' ) ) {
	define( 'MCKR_VERSION', '1.1.0' );
}

function mckr_table( $suffix ) {
	global $wpdb;
	return $wpdb->prefix . 'mck_' . $suffix;
}

function mckr_base_tables_ready() {
	global $wpdb;

	foreach ( [ 'campaigns', 'comments', 'draws' ] as $suffix ) {
		$table = mckr_table( $suffix );
		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( $found !== $table ) {
			return false;
		}
	}

	return true;
}

function mckr_audit_table() {
	global $wpdb;
	return $wpdb->prefix . 'mck_redraw_audit';
}

function mckr_ensure_audit_table() {
	global $wpdb;

	$table = mckr_audit_table();
	$found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
	);

	if ( $found === $table ) {
		return true;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		campaign_id bigint(20) unsigned NOT NULL,
		draw_id bigint(20) unsigned NOT NULL,
		result_type varchar(20) NOT NULL,
		position_no int(10) unsigned NOT NULL,
		old_comment_id varchar(100) NOT NULL,
		old_username varchar(190) NOT NULL,
		old_comment_text longtext NOT NULL,
		old_verification_status varchar(20) NOT NULL,
		old_verification_note text NULL,
		new_comment_id varchar(100) NOT NULL,
		new_username varchar(190) NOT NULL,
		new_comment_text longtext NOT NULL,
		redraw_seed varchar(128) NOT NULL,
		new_score_hash varchar(128) NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY campaign_id (campaign_id),
		KEY draw_id (draw_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );

	$found = $wpdb->get_var(
		$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
	);

	return $found === $table;
}

function mckr_unresolved_draws( $limit = 20, $campaign_id = 0 ) {
	global $wpdb;

	if ( ! mckr_base_tables_ready() ) {
		return [];
	}

	$limit = max( 1, min( 100, absint( $limit ) ) );
	$draws = mckr_table( 'draws' );
	$campaigns = mckr_table( 'campaigns' );

	$filter = $campaign_id ? $wpdb->prepare(' AND d.campaign_id = %d',absint($campaign_id)) : '';
	$sql = $wpdb->prepare(
		"SELECT
			d.id,
			d.campaign_id,
			d.result_type,
			d.position_no,
			d.comment_id,
			d.username,
			d.comment_text,
			d.verification_status,
			d.verification_note,
			d.drawn_at,
			c.title AS campaign_title
		FROM {$draws} d
		INNER JOIN {$campaigns} c ON c.id = d.campaign_id
		WHERE d.verification_status = %s
		  AND d.result_type IN ('winner','reserve') {$filter}
		ORDER BY d.id ASC
		LIMIT %d",
		'disqualified',
		$limit
	);

	return (array) $wpdb->get_results( $sql, ARRAY_A );
}

function mckr_recent_audit( $limit = 10, $campaign_id = 0 ) {
	global $wpdb;

	if ( $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',mckr_audit_table())) !== mckr_audit_table() ) {
		return [];
	}

	$limit = max( 1, min( 50, absint( $limit ) ) );
	$table = mckr_audit_table();
	$campaigns = mckr_table( 'campaigns' );
	$filter = $campaign_id ? $wpdb->prepare(' WHERE a.campaign_id = %d',absint($campaign_id)) : '';

	$sql = $wpdb->prepare(
		"SELECT
			a.id,
			a.campaign_id,
			c.title AS campaign_title,
			a.draw_id,
			a.result_type,
			a.position_no,
			a.old_username,
			a.new_username,
			a.created_at,
			a.actor_user_id
		FROM {$table} a
		LEFT JOIN {$campaigns} c ON c.id = a.campaign_id {$filter}
		ORDER BY a.id DESC
		LIMIT %d",
		$limit
	);

	return (array) $wpdb->get_results( $sql, ARRAY_A );
}

function mckr_normalize_username( $username ) {
	return strtolower( ltrim( trim( (string) $username ), '@' ) );
}

function mckr_redraw_one_locked( $draw_id, $actor_user_id ) {
	global $wpdb;

	$draw_id = absint( $draw_id );
	$actor_user_id = absint( $actor_user_id );

	if ( ! $draw_id ) {
		return new WP_Error( 'mckr_invalid_draw', 'Geçerli çekiliş satırı bulunamadı.' );
	}

	if ( ! mckr_base_tables_ready() ) {
		return new WP_Error( 'mckr_tables_missing', 'Madagaskar çekiliş tabloları bulunamadı.' );
	}

	if ( ! mckr_ensure_audit_table() ) {
		return new WP_Error( 'mckr_audit_table', 'Yeniden seçim denetim tablosu oluşturulamadı.' );
	}

	$draws = mckr_table( 'draws' );
	$campaigns = mckr_table( 'campaigns' );
	$comments = mckr_table( 'comments' );
	$audit = mckr_audit_table();


	try {
		$draw = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$draws} WHERE id = %d FOR UPDATE",
				$draw_id
			)
		);

		if ( ! $draw ) {
			throw new RuntimeException( 'Çekiliş sonucu bulunamadı.' );
		}

		if ( 'disqualified' !== (string) $draw->verification_status ) {
			throw new RuntimeException( 'Bu sonuç yeniden seçim beklemiyor.' );
		}

		if ( ! in_array( (string) $draw->result_type, [ 'winner', 'reserve' ], true ) ) {
			throw new RuntimeException( 'Yalnız asil veya yedek sonuç yeniden seçilebilir.' );
		}

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$campaigns} WHERE id = %d FOR UPDATE",
				absint( $draw->campaign_id )
			)
		);

		if ( ! $campaign ) {
			throw new RuntimeException( 'Kampanya bulunamadı.' );
		}

		$existing_draws = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, comment_id, username, verification_status
				 FROM {$draws}
				 WHERE campaign_id = %d",
				absint( $draw->campaign_id )
			)
		);

		if ($wpdb->last_error) { throw new RuntimeException('Mevcut kazananlar okunamadı.'); }

		$used_comment_ids = [];
		$banned_users = [];

		foreach ( $existing_draws as $existing ) {
			$cid = trim( (string) $existing->comment_id );
			if ( '' !== $cid ) {
				$used_comment_ids[ $cid ] = true;
			}

			$user_key = mckr_normalize_username( $existing->username );
			if ( '' === $user_key ) {
				continue;
			}

			$banned_users[ $user_key ] = true;
		}

        // Old occupants leave the current slot; keep their identity excluded forever.
        $history = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT old_comment_id,old_username,new_comment_id,new_username FROM {$audit} WHERE campaign_id = %d",
            absint( $draw->campaign_id )
        ) );
        if ($wpdb->last_error) { throw new RuntimeException('Geçmiş kazananlar okunamadı.'); }
        foreach ( $history as $previous ) {
            foreach ( array( 'old', 'new' ) as $kind ) {
                $cid = trim( (string) $previous->{$kind . '_comment_id'} );
                $user = mckr_normalize_username( $previous->{$kind . '_username'} );
                if ( '' !== $cid ) { $used_comment_ids[$cid] = true; }
                if ( '' !== $user ) { $banned_users[$user] = true; }
            }
        }

		$candidates = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, comment_id, username, comment_text, normalized_text
				 FROM {$comments}
				 WHERE campaign_id = %d
				   AND is_valid = 1
				 ORDER BY id ASC",
				absint( $draw->campaign_id )
			)
		);

		if ($wpdb->last_error) { throw new RuntimeException('Uygun adaylar okunamadı.'); }

		$redraw_seed = hash(
			'sha256',
			(string) $campaign->audit_seed . '|' .
			(string) $campaign->eligible_hash . '|redraw|' .
			(string) $draw->id . '|' .
			(string) $draw->comment_id . '|' .
			(string) $draw->username . '|' .
			(string) $draw->result_type . '|' .
			(string) $draw->position_no
		);

		$pool = [];

		foreach ( $candidates as $candidate ) {
			$comment_id = trim( (string) $candidate->comment_id );
			$user_key = mckr_normalize_username( $candidate->username );

			if ( '' === $comment_id || '' === $user_key ) {
				continue;
			}

			if ( isset( $used_comment_ids[ $comment_id ] ) ) {
				continue;
			}

			if ( isset( $banned_users[ $user_key ] ) ) {
				continue;
			}

			$score = hash(
				'sha256',
				$redraw_seed . '|' .
				$comment_id . '|' .
				$user_key . '|' .
				(string) $candidate->normalized_text
			);

			$item = [
				'comment_id'   => $comment_id,
				'username'     => (string) $candidate->username,
				'comment_text' => (string) $candidate->comment_text,
				'user_key'     => $user_key,
				'score'        => $score,
			];

			if ( ! empty( $campaign->one_user_one_entry ) ) {
				if (
					! isset( $pool[ $user_key ] ) ||
					strcmp( $score, $pool[ $user_key ]['score'] ) < 0
				) {
					$pool[ $user_key ] = $item;
				}
			} else {
				$pool[] = $item;
			}
		}

		if ( empty( $pool ) ) {
			throw new RuntimeException( 'Yeterli yeni uygun aday bulunamadı.' );
		}

		if ( ! empty( $campaign->one_user_one_entry ) ) {
			$pool = array_values( $pool );
		}

		usort(
			$pool,
			static function ( $a, $b ) {
				$cmp = strcmp( $a['score'], $b['score'] );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return strcmp( $a['comment_id'], $b['comment_id'] );
			}
		);

		$selected = $pool[0];
		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$audit,
			[
				'campaign_id'            => absint( $draw->campaign_id ),
				'draw_id'                => absint( $draw->id ),
				'result_type'            => (string) $draw->result_type,
				'position_no'            => absint( $draw->position_no ),
				'old_comment_id'         => (string) $draw->comment_id,
				'old_username'           => (string) $draw->username,
				'old_comment_text'       => (string) $draw->comment_text,
				'old_verification_status'=> (string) $draw->verification_status,
				'old_verification_note'  => (string) $draw->verification_note,
				'new_comment_id'         => $selected['comment_id'],
				'new_username'           => $selected['username'],
				'new_comment_text'       => $selected['comment_text'],
				'redraw_seed'            => $redraw_seed,
				'new_score_hash'         => $selected['score'],
				'actor_user_id'          => $actor_user_id,
				'created_at'             => $now,
			],
			[
				'%d','%d','%s','%d','%s','%s','%s','%s','%s',
				'%s','%s','%s','%s','%s','%d','%s'
			]
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Denetim kaydı yazılamadı.' );
		}

		$audit_id = absint( $wpdb->insert_id );

		$updated = $wpdb->update(
			$draws,
			[
				'comment_id'          => $selected['comment_id'],
				'username'            => $selected['username'],
				'comment_text'        => $selected['comment_text'],
				'verification_status' => 'pending',
				'verification_note'   => sprintf(
					'Yeniden seçim #%d: önceki kişi doğrulanmadığı için bu pozisyon için tek yeni kişi seçildi.',
					$audit_id
				),
				'score_hash'          => $selected['score'],
				'drawn_at'            => $now,
			],
			[ 'id' => $draw_id ],
			[ '%s','%s','%s','%s','%s','%s','%s' ],
			[ '%d' ]
		);

		if ( false === $updated || 1 !== (int) $updated ) {
			throw new RuntimeException( 'Kazanan satırı güncellenemedi.' );
		}

		$campaign_updated = $wpdb->update(
			$campaigns,
			[ 'updated_at' => $now ],
			[ 'id' => absint( $draw->campaign_id ) ],
			[ '%s' ],
			[ '%d' ]
		);


        if (false === $campaign_updated) { throw new RuntimeException('Kampanya zamanı kaydedilemedi.'); }

		return [
			'ok'              => true,
			'audit_id'        => $audit_id,
			'campaign_id'     => absint( $draw->campaign_id ),
			'campaign_title'  => (string) $campaign->title,
			'draw_id'         => $draw_id,
			'result_type'     => (string) $draw->result_type,
			'position_no'     => absint( $draw->position_no ),
			'old_username'    => (string) $draw->username,
			'new_username'    => $selected['username'],
			'new_comment_id'  => $selected['comment_id'],
			'new_score_hash'  => $selected['score'],
			'pool_size'       => count( $pool ),
		];

	} catch ( Throwable $e ) {

		return new WP_Error(
			'mckr_redraw_failed',
			$e->getMessage(),
			[ 'draw_id' => $draw_id ]
		);
	}
}

function mckr_is_draw_screen( $screen = null ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$screen_id = '';

	if ( $screen && is_object( $screen ) && isset( $screen->id ) ) {
		$screen_id = sanitize_key( (string) $screen->id );
	}

	$haystack = $page . ' ' . $screen_id;

	return (
		false !== strpos( $haystack, 'cekilis' ) ||
		false !== strpos( $haystack, 'mck' ) ||
		false !== strpos( $haystack, 'madagaskar-cek' )
	);
}

function mckr_set_notice( $type, $message ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return;
	}

	set_transient(
		'mckr_notice_' . $user_id,
		[
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
		],
		120
	);
}


/** Atomic replacement of explicit slots; campaign lock serializes all replacements. */
function mckr_redraw_slots( array $draw_ids, $actor_user_id = 0, array $expected = array() ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'mckr_forbidden', 'Bu işlem için yetkiniz yok.' );
    }
    $draw_ids = array_values( array_unique( array_filter( array_map( 'absint', $draw_ids ) ) ) );
    sort( $draw_ids );
    if ( ! $draw_ids || count($draw_ids) > 100 ) {
        return new WP_Error( 'mckr_invalid_draw', 'Geçerli eksik slotları seçin.' );
    }
    if ( ! mckr_base_tables_ready() || ! mckr_ensure_audit_table() ) {
        return new WP_Error( 'mckr_tables_missing', 'Çekiliş denetim tabloları bulunamadı.' );
    }
    // Never claim rollback on a nontransactional installation.
    foreach ( array('campaigns','comments','draws','redraw_audit') as $suffix ) {
        $table = $wpdb->get_row( $wpdb->prepare('SHOW TABLE STATUS LIKE %s', mckr_table($suffix)) );
        if ( ! $table || 'innodb' !== strtolower((string)$table->Engine) ) {
            return new WP_Error('mckr_transaction_required','Güvenli yeniden seçim için InnoDB gereklidir.');
        }
    }
    $draws = mckr_table('draws'); $campaigns = mckr_table('campaigns');
    $campaign_id = absint($wpdb->get_var($wpdb->prepare("SELECT campaign_id FROM {$draws} WHERE id = %d",$draw_ids[0])));
    if ( ! $campaign_id ) { return new WP_Error('mckr_invalid_draw','Çekiliş sonucu bulunamadı.'); }
    if ( false === $wpdb->query('START TRANSACTION') ) { return new WP_Error('mckr_transaction','İşlem başlatılamadı.'); }
    try {
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$campaigns} WHERE id = %d FOR UPDATE",$campaign_id));
        if ( ! $campaign || 'drawn' !== (string)$campaign->status ) { throw new RuntimeException('Sonuçlandırılmış çekiliş bulunamadı.'); }
        // Validate every requested occupant before changing any slot.
        foreach ($draw_ids as $id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$draws} WHERE id = %d FOR UPDATE",$id));
            if (!$row || (int)$row->campaign_id !== $campaign_id || 'disqualified' !== (string)$row->verification_status) {
                throw new RuntimeException('Bu sonuç yeniden seçim beklemiyor; sayfayı yenileyin.');
            }
            if ($expected && (!isset($expected[$id]) || (string)$expected[$id] !== (string)$row->comment_id)) {
                throw new RuntimeException('Kazanan değişmiş; sayfayı yenileyin.');
            }
        }
        $results = array();
        foreach ($draw_ids as $id) {
            $result = mckr_redraw_one_locked($id,get_current_user_id());
            if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
            $results[] = $result;
        }
        if (false === $wpdb->query('COMMIT')) { throw new RuntimeException('İşlem kaydedilemedi.'); }
        return array('ok'=>true,'count'=>count($results),'results'=>$results);
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('mckr_redraw_failed',$e->getMessage());
    }
}

function mckr_redraw_one( $draw_id, $actor_user_id = 0 ) {
    $result = mckr_redraw_slots(array($draw_id),$actor_user_id);
    return is_wp_error($result) ? $result : $result['results'][0];
}

function mckr_render_replacement_controls() {
    if (!current_user_can('manage_options')) { return; }
    $notice = get_transient('mckr_notice_'.get_current_user_id());
    if ($notice) {
        delete_transient('mckr_notice_'.get_current_user_id());
        $type = in_array($notice['type'],array('success','error','warning','info'),true) ? $notice['type'] : 'info';
        echo '<div class="notice notice-'.esc_attr($type).'"><p>'.esc_html($notice['message']).'</p></div>';
    }
    if (!mckr_is_draw_screen(get_current_screen())) { return; }
    $campaign_filter = isset($_GET['campaign_id']) ? absint($_GET['campaign_id']) : 0;
    // Filtering happens in SQL so older campaigns cannot hide the current campaign's slots.
    $pending = mckr_unresolved_draws(100,$campaign_filter);
    echo '<div class="notice notice-info"><p><strong>Manuel yeniden seçim aktif.</strong> Doğrulanmış kazananlar korunur; yalnız şartı sağlamayan seçili slotlar doldurulur.</p>';
    $groups = array();
    foreach ($pending as $row) { $groups[$row['campaign_id']][]=$row; }
    foreach ($groups as $rows) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="mckr_redraw">';
        wp_nonce_field('mckr_redraw_slots');
        foreach ($rows as $row) {
            echo '<input type="hidden" name="draw_ids[]" value="'.absint($row['id']).'">';
            echo '<input type="hidden" name="expected['.absint($row['id']).']" value="'.esc_attr($row['comment_id']).'">';
        }
        echo '<p>'.esc_html($rows[0]['campaign_title']).' <button class="button button-secondary">'.count($rows).' Kişi İçin Yeniden Çek</button></p></form>';
        foreach ($rows as $row) {
            if (count($rows) < 2) { continue; }
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo '<input type="hidden" name="action" value="mckr_redraw">';wp_nonce_field('mckr_redraw_slots');
            echo '<input type="hidden" name="draw_ids[]" value="'.absint($row['id']).'">';
            echo '<input type="hidden" name="expected['.absint($row['id']).']" value="'.esc_attr($row['comment_id']).'">';
            echo '<p>'.esc_html('winner' === $row['result_type'] ? 'Asil' : 'Yedek').' #'.absint($row['position_no']).' <button class="button">1 Kişi İçin Yeniden Çek</button></p></form>';
        }
    }
    $history = mckr_recent_audit(20,$campaign_filter);
    if ($history) {
        echo '<h3>Yeniden seçim geçmişi</h3><ul>';
        foreach ($history as $row) {
            echo '<li>'.esc_html($row['campaign_title']).' — '.absint($row['position_no']).': @'.esc_html($row['old_username']).' (disqualified) → @'.esc_html($row['new_username']).' · '.esc_html($row['created_at']).' · Yönetici #'.absint($row['actor_user_id']).'</li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}
add_action('admin_notices','mckr_render_replacement_controls');

add_action('admin_post_mckr_redraw',function(){
    if (!current_user_can('manage_options')) { wp_die('Bu işlem için yetkiniz yok.',403); }
    if ('POST' !== strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'))) { wp_die('Yeniden seçim yalnız POST ile yapılabilir.',405); }
    check_admin_referer('mckr_redraw_slots');
    $ids = isset($_POST['draw_ids']) && is_array($_POST['draw_ids']) ? wp_unslash($_POST['draw_ids']) : array();
    $expected = isset($_POST['expected']) && is_array($_POST['expected']) ? wp_unslash($_POST['expected']) : array();
    if (!$expected) { wp_die('Kazanan snapshot eksik; sayfayı yenileyin.',400); }
    $result = mckr_redraw_slots($ids,get_current_user_id(),$expected);
    mckr_set_notice(is_wp_error($result) ? 'error' : 'success',is_wp_error($result) ? $result->get_error_message() : $result['count'].' eksik slot için yeniden seçim tamamlandı.');
    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=madagaskar-cekilis'));
    exit;
});
