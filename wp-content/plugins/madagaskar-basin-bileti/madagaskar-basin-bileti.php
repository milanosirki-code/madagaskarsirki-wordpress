<?php
/**
 * Plugin Name: Madagaskar Basın Bileti Talepleri
 * Description: Gizli bağlantıyla açılan basın bileti talep formu, yönetici onayı ve onay sonrası mevcut Protokol / Ücretsiz Bilet altyapısıyla (MDG_Protocol_Tickets) QR bilet üretimi ve e-posta gönderimi. Ücretli checkout, ürünler ve bilet şablonları değiştirilmez.
 * Version: 1.0.1
 * Author: Madagaskar Sirki
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('MDG_Press_Tickets', false)) {
final class MDG_Press_Tickets {
	const VERSION = '1.0.1';
	const PAGE = 'mdg-press-tickets';
	const ACCESS_OPTION = 'mdg_press_access_key_v1';
	const INDEX = 'mdg_press_req_index_v1';
	const PREFIX = 'mdg_press_req_';
	const MAX_PER_ORG_SESSION = 2;
	const ROLES = array('Muhabir', 'Kameraman', 'Foto muhabiri', 'Editör');
	const NOTIFY_FALLBACK = 'madagaskarsirkiturkiye@gmail.com';
	const WHATSAPP = '0312 911 37 10';

	static function hooks() {
		add_action('admin_menu', function () {
			add_submenu_page('mmc-dashboard', 'Basın Bileti Talepleri', 'Basın Bileti Talepleri', 'manage_woocommerce', self::PAGE, array(__CLASS__, 'admin_page'));
		}, 100);
		foreach (array('approve', 'reject', 'rotate') as $action) add_action('admin_post_mdg_press_' . $action, array(__CLASS__, 'admin_' . $action));
		add_action('template_redirect', array(__CLASS__, 'public_route'), 1);
	}

	/* ---------- pure helpers (unit tested) ---------- */

	static function org_key($org) {
		$s = function_exists('remove_accents') ? remove_accents((string) $org) : (string) $org;
		$s = mb_strtolower(trim($s), 'UTF-8');
		$s = preg_replace('/\b(gazetesi|gazete|haber|haberleri|medya|tv|com|net|tr|www)\b/u', ' ', $s);
		$s = preg_replace('/[^a-z0-9]+/u', '', $s);
		return $s;
	}

	static function valid_key($given, $stored) {
		return is_string($given) && is_string($stored) && strlen($stored) >= 32 && hash_equals($stored, $given);
	}

	/** Validates 1–2 press people. Returns list of array(name, role). */
	static function people($names, $roles) {
		$names = array_values((array) $names); $roles = array_values((array) $roles); $out = array();
		foreach ($names as $i => $raw) {
			$name = trim(sanitize_text_field((string) $raw));
			if ($name === '') continue;
			if (mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 80) throw new Exception('Ad soyad 3–80 karakter olmalı.');
			$role = (string) ($roles[$i] ?? '');
			if (!in_array($role, self::ROLES, true)) throw new Exception('Görev seçimi geçersiz.');
			$out[] = array('name' => $name, 'role' => $role);
		}
		if (!$out) throw new Exception('En az bir kişi yazın.');
		if (count($out) > self::MAX_PER_ORG_SESSION) throw new Exception('Bir kuruluşa bir seans için en fazla ' . self::MAX_PER_ORG_SESSION . ' basın bileti verilebilir.');
		return $out;
	}

	/** Counts people already requested (pending/approved/error) for same org or same email in the session. */
	static function used_for($requests, $session_id, $org_key, $email) {
		$used = 0; $email = strtolower(trim((string) $email));
		foreach ((array) $requests as $r) {
			if (!is_array($r) || (int) ($r['session'] ?? 0) !== (int) $session_id) continue;
			if (in_array($r['state'] ?? '', array('rejected'), true)) continue;
			if (($r['org_key'] ?? '') === $org_key || strtolower((string) ($r['email'] ?? '')) === $email) $used += count((array) ($r['people'] ?? array()));
		}
		return $used;
	}

	static function check_limit($requests, $session_id, $org_key, $email, $new_count, $ignore_id = '') {
		$filtered = array();
		foreach ((array) $requests as $r) if (($r['id'] ?? '') !== $ignore_id) $filtered[] = $r;
		$used = self::used_for($filtered, $session_id, $org_key, $email);
		if ($used + $new_count > self::MAX_PER_ORG_SESSION) {
			$left = max(0, self::MAX_PER_ORG_SESSION - $used);
			throw new Exception($left ? 'Bu kuruluş için bu seansta en fazla ' . $left . ' basın bileti daha talep edilebilir.' : 'Bu kuruluş bu seans için basın bileti hakkını kullandı.');
		}
	}

	/** Returns id of an identical open request (same session, e-mail and people), or ''. */
	static function duplicate_of($requests, $session_id, $email, $people) {
		$sig = function ($ppl) { $n = array(); foreach ((array) $ppl as $p) $n[] = mb_strtolower(trim((string) ($p['name'] ?? '')), 'UTF-8'); sort($n); return implode('|', $n); };
		$want = $sig($people); $email = strtolower(trim((string) $email));
		foreach ((array) $requests as $r) {
			if (!is_array($r) || (int) ($r['session'] ?? 0) !== (int) $session_id) continue;
			if (!in_array($r['state'] ?? '', array('pending', 'approved', 'error'), true)) continue;
			if (strtolower((string) ($r['email'] ?? '')) === $email && $sig($r['people'] ?? array()) === $want) return (string) $r['id'];
		}
		return '';
	}

	/** Builds a MDG_Protocol_Tickets batch array from an approved request. */
	static function batch_from_request($r, $session, $event_name, $venue, $user_id, $batch_id) {
		$people = array();
		foreach ($r['people'] as $p) $people[] = array('name' => $p['name'] . ' (Basın · ' . $p['role'] . ' · ' . $r['org'] . ')', 'phone' => '', 'seat' => '', 'section' => '');
		return array(
			'id' => $batch_id, 'created' => time(), 'user' => (int) $user_id,
			'title' => 'Basın — ' . $r['org'], 'kind' => 'protocol', 'mode' => 'named',
			'recipient_phone' => (string) ($r['phone'] ?? ''), 'event' => (int) $r['event'],
			'event_name' => $event_name, 'venue' => $venue,
			'sessions' => array((int) $r['session'] => $session), 'people' => $people,
			'orders' => array(), 'tickets' => array(), 'state' => 'preview', 'press_request' => $r['id'],
		);
	}

	/* ---------- storage ---------- */

	static function all_requests() {
		$out = array();
		foreach ((array) get_option(self::INDEX, array()) as $id) { $r = get_option(self::PREFIX . $id, array()); if ($r) $out[$id] = $r; }
		return $out;
	}
	static function load($id) {
		if (!preg_match('/^[a-f0-9]{32}$/D', (string) $id)) throw new Exception('Talep kimliği geçersiz.');
		$r = get_option(self::PREFIX . $id, array()); if (!$r) throw new Exception('Talep bulunamadı.'); return $r;
	}
	static function save($r) {
		update_option(self::PREFIX . $r['id'], $r, false);
		$index = (array) get_option(self::INDEX, array());
		if (!in_array($r['id'], $index, true)) { $index[] = $r['id']; update_option(self::INDEX, $index, false); }
	}
	static function access_key() {
		$k = get_option(self::ACCESS_OPTION, '');
		if (!is_string($k) || strlen($k) < 32) { $k = bin2hex(random_bytes(24)); update_option(self::ACCESS_OPTION, $k, false); }
		return $k;
	}
	static function form_url() { return add_query_arg(array('mdg_basin' => 'talep', 'anahtar' => self::access_key()), home_url('/')); }

	static function ready() {
		return class_exists('MDG_Protocol_Tickets') && method_exists('MDG_Protocol_Tickets', 'catalogue') && method_exists('MDG_Protocol_Tickets', 'issue_batch');
	}
	static function catalogue() { return self::ready() ? MDG_Protocol_Tickets::catalogue() : array(); }

	static function notify_to() {
		$to = apply_filters('mdg_press_notify_to', self::NOTIFY_FALLBACK);
		return is_email($to) ? $to : get_option('admin_email');
	}

	static function mail($to, $subject, $html) {
		return wp_mail($to, $subject, $html, array('Content-Type: text/html; charset=UTF-8'));
	}

	/* ---------- public form ---------- */

	static function public_route() {
		if (!isset($_GET['mdg_basin'])) return;
		nocache_headers();
		header('Cache-Control: no-store, private, max-age=0');
		header('X-Robots-Tag: noindex, nofollow');
		header('Referrer-Policy: no-referrer');
		$key = is_string($_GET['anahtar'] ?? null) ? wp_unslash($_GET['anahtar']) : '';
		if (!self::valid_key($key, (string) get_option(self::ACCESS_OPTION, ''))) { status_header(404); echo 'Sayfa bulunamadı.'; exit; }
		$msg = ''; $ok = false;
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
			try { self::submit(); wp_safe_redirect(add_query_arg(array('mdg_basin' => 'talep', 'anahtar' => $key, 'gonderildi' => '1'), home_url('/')), 303); exit; } catch (Throwable $e) { $msg = $e->getMessage(); }
		} elseif (($_GET['gonderildi'] ?? '') === '1') $ok = true;
		self::render_form($key, $msg, $ok);
		exit;
	}

	static function rate_limited() {
		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
		$k = 'mdg_press_rl_' . substr(hash('sha256', $ip . wp_salt('nonce')), 0, 20);
		$n = (int) get_transient($k);
		if ($n >= 6) return true;
		set_transient($k, $n + 1, HOUR_IN_SECONDS);
		return false;
	}

	static function submit() {
		if (!wp_verify_nonce((string) ($_POST['_mdg_press'] ?? ''), 'mdg_press_submit')) throw new Exception('Form süresi doldu; sayfayı yenileyip tekrar deneyin.');
		if (trim((string) ($_POST['website'] ?? '')) !== '') throw new Exception('Talep alınamadı.');
		if (self::rate_limited()) throw new Exception('Çok fazla deneme yapıldı; lütfen bir saat sonra tekrar deneyin.');
		if (!self::ready()) throw new Exception('Bilet sistemi şu an hazır değil; lütfen WhatsApp ' . self::WHATSAPP . ' üzerinden yazın.');
		$org = sanitize_text_field(wp_unslash($_POST['org'] ?? ''));
		if (mb_strlen($org, 'UTF-8') < 2 || mb_strlen($org, 'UTF-8') > 120) throw new Exception('Yayın kuruluşu adını yazın.');
		$web = esc_url_raw(wp_unslash($_POST['web'] ?? ''));
		$email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
		if (!is_email($email)) throw new Exception('Geçerli bir e-posta adresi yazın.');
		$phone = MDG_Protocol_Tickets::phone(wp_unslash($_POST['phone'] ?? ''));
		if ($phone === '') throw new Exception('İletişim için cep telefonu yazın.');
		if (empty($_POST['consent'])) throw new Exception('KVKK aydınlatma metnini onaylayın.');
		$people = self::people(wp_unslash((array) ($_POST['names'] ?? array())), wp_unslash((array) ($_POST['roles'] ?? array())));
		list($event, $session) = array_map('absint', explode(':', (string) ($_POST['session'] ?? '0:0') . ':0'));
		$cat = self::catalogue();
		if (!isset($cat[$event]['sessions'][$session])) throw new Exception('Seçilen seans artık talep alınabilir durumda değil.');
		if ((int) $cat[$event]['sessions'][$session]['available'] < count($people)) throw new Exception('Bu seansta yeterli kapasite kalmadı.');
		$org_key = self::org_key($org);
		$lock = 'mdg_press_submit_lock_' . $session;
		if (!add_option($lock, time(), '', 'no')) { $t = (int) get_option($lock); if ($t && time() - $t < 30) throw new Exception('Sistem yoğun; birkaç saniye sonra tekrar gönderin.'); update_option($lock, time(), false); }
		try {
			if (self::duplicate_of(self::all_requests(), $session, $email, $people) !== '') return false;
			self::check_limit(self::all_requests(), $session, $org_key, $email, count($people));
			$e = $cat[$event]['event'];
			$r = array(
				'id' => bin2hex(random_bytes(16)), 'created' => time(), 'state' => 'pending',
				'org' => $org, 'org_key' => $org_key, 'web' => $web, 'email' => $email, 'phone' => $phone,
				'event' => $event, 'session' => $session, 'event_name' => (string) $e->title, 'venue' => (string) $e->venue_name,
				'time' => (string) $cat[$event]['sessions'][$session]['time'], 'people' => $people,
				'note' => sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')),
			);
			self::save($r);
		} finally { delete_option($lock); }
		self::notify_new($r);
		return true;
	}

	static function notify_new($r) {
		$org = $r['org']; $email = $r['email']; $people = $r['people']; $phone = $r['phone']; $web = $r['web'];
		$admin = add_query_arg(array('page' => self::PAGE), admin_url('admin.php'));
		$list = ''; foreach ($people as $p) $list .= '<li>' . esc_html($p['name'] . ' — ' . $p['role']) . '</li>';
		self::mail(self::notify_to(), 'Yeni basın bileti talebi: ' . $org, '<p><b>' . esc_html($org) . '</b> basın bileti talep etti.</p><p>' . esc_html($r['event_name'] . ' · ' . $r['venue'] . ' · ' . $r['time']) . '</p><ul>' . $list . '</ul><p>E-posta: ' . esc_html($email) . ' · Tel: ' . esc_html($phone) . ($web ? ' · Web: ' . esc_html($web) : '') . '</p><p><a href="' . esc_url($admin) . '">Talebi onayla veya reddet</a></p>');
		self::mail($email, 'Basın bileti talebiniz alındı — Madagaskar Sirki', '<p>Merhaba,</p><p>' . esc_html($r['event_name'] . ' (' . $r['time'] . ')') . ' için basın bileti talebiniz bize ulaştı. Onaylandığında QR kodlu biletleriniz bu adrese e-postayla gönderilecek.</p><p>Sorularınız için WhatsApp: ' . esc_html(self::WHATSAPP) . '</p><p>Madagaskar Sirki Türkiye</p>');
	}

	static function render_form($key, $msg, $ok) {
		$cat = self::catalogue();
		$action = esc_url(add_query_arg(array('mdg_basin' => 'talep', 'anahtar' => $key), home_url('/')));
		echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Basın Bileti Talebi · Madagaskar Sirki</title>';
		echo '<style>body{font:16px/1.5 system-ui,sans-serif;max-width:720px;margin:0 auto;padding:24px;background:#fff9ed;color:#222}h1{color:#a63225;font-size:26px}label{display:block;margin:14px 0 4px;font-weight:600}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;font:inherit;border:1px solid #bbb;border-radius:6px;background:#fff}.row{display:grid;grid-template-columns:2fr 1fr;gap:10px}.msg{padding:12px;border-radius:6px;margin:14px 0}.err{background:#fde2e1}.ok{background:#e3f4e1}button{margin-top:18px;padding:12px 22px;font:inherit;font-weight:700;background:#a63225;color:#fff;border:0;border-radius:7px}.hp{position:absolute;left:-9999px}small{color:#555}.chk{display:flex;gap:8px;align-items:flex-start;font-weight:400}.chk input{width:auto;margin-top:4px}</style></head><body>';
		echo '<h1>Basın Bileti Talebi</h1><p>Madagaskar Sirki gösterilerini haberleştirecek yayın kuruluşları için. Her kuruluşa bir seans için en fazla <b>2 basın bileti</b> (ör. muhabir ve kameraman) verilir. Talebiniz onaylandığında QR kodlu biletler e-posta adresinize gönderilir.</p>';
		if ($ok) { echo '<div class="msg ok"><b>Talebiniz alındı.</b> Onay sonrasında biletleriniz e-postanıza gönderilecek. Sorular için WhatsApp: ' . esc_html(self::WHATSAPP) . '</div></body></html>'; return; }
		if ($msg !== '') echo '<div class="msg err">' . esc_html($msg) . '</div>';
		if (!$cat) { echo '<div class="msg err">Şu an basın bileti talebine açık gösteri bulunmuyor. Bilgi için WhatsApp: ' . esc_html(self::WHATSAPP) . '</div></body></html>'; return; }
		$v = function ($k) { return esc_attr(sanitize_text_field(wp_unslash($_POST[$k] ?? ''))); };
		echo '<form method="post" action="' . $action . '" onsubmit="var b=this.querySelector(\'button[type=submit]\');if(b.disabled)return false;b.disabled=true;b.textContent=\'Gönderiliyor…\';">';
		echo '<input type="hidden" name="_mdg_press" value="' . esc_attr(wp_create_nonce('mdg_press_submit')) . '"><div class="hp"><label>Web sitesi<input name="website" tabindex="-1" autocomplete="off"></label></div>';
		echo '<label for="s">Gösteri ve seans</label><select id="s" name="session" required><option value="">Seçin</option>';
		$sel = (string) ($_POST['session'] ?? '');
		foreach ($cat as $eid => $row) {
			echo '<optgroup label="' . esc_attr($row['event']->title) . '">';
			foreach ($row['sessions'] as $sid => $s) { $val = (int) $eid . ':' . (int) $sid; echo '<option value="' . esc_attr($val) . '"' . selected($sel, $val, false) . '>' . esc_html($row['event']->venue_name . ' — ' . $s['time']) . '</option>'; }
			echo '</optgroup>';
		}
		echo '</select>';
		echo '<label for="o">Yayın kuruluşu</label><input id="o" name="org" maxlength="120" required value="' . $v('org') . '" placeholder="Örn. Gazete Ayvalık">';
		echo '<label for="w">Web sitesi (isteğe bağlı)</label><input id="w" name="web" type="url" maxlength="200" value="' . esc_attr(esc_url_raw(wp_unslash($_POST['web'] ?? ''))) . '" placeholder="https://">';
		echo '<label for="e">Kurumsal e-posta (biletler bu adrese gönderilir)</label><input id="e" name="email" type="email" required maxlength="120" value="' . $v('email') . '">';
		echo '<label for="p">Cep telefonu</label><input id="p" name="phone" type="tel" required maxlength="25" value="' . $v('phone') . '" placeholder="05xxxxxxxxx">';
		$names = (array) ($_POST['names'] ?? array()); $roles = (array) ($_POST['roles'] ?? array());
		for ($i = 0; $i < self::MAX_PER_ORG_SESSION; $i++) {
			echo '<label>' . ($i + 1) . '. kişi' . ($i ? ' (isteğe bağlı)' : '') . '</label><div class="row"><input name="names[]" maxlength="80"' . ($i ? '' : ' required') . ' placeholder="Ad soyad" value="' . esc_attr(sanitize_text_field(wp_unslash($names[$i] ?? ''))) . '"><select name="roles[]">';
			foreach (self::ROLES as $j => $role) echo '<option' . selected((string) ($roles[$i] ?? self::ROLES[$i ? 1 : 0]), $role, false) . '>' . esc_html($role) . '</option>';
			echo '</select></div>';
		}
		echo '<label for="n">Not (isteğe bağlı)</label><textarea id="n" name="note" rows="3" maxlength="500">' . esc_textarea(sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''))) . '</textarea>';
		echo '<label class="chk"><input type="checkbox" name="consent" value="1" required> <span>Ad, iletişim ve kuruluş bilgilerimin basın bileti üretimi ve gösteri girişi için işlenmesine ilişkin <a href="' . esc_url(home_url('/kvkk-aydinlatma-metni/')) . '" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>\'ni okudum.</span></label>';
		echo '<button type="submit">Talebi gönder</button><p><small>Biletler serbest oturma düzenindedir ve yalnız seçilen seans için geçerlidir. Talepler gösteri başlayana kadar alınır.</small></p></form></body></html>';
	}

	/* ---------- admin ---------- */

	static function guard() { if (!current_user_can('manage_woocommerce')) wp_die('Yetkisiz işlem.'); }
	static function admin_url_page($args = array()) { return add_query_arg(array_merge(array('page' => self::PAGE), $args), admin_url('admin.php')); }

	static function admin_page() {
		self::guard();
		echo '<div class="wrap"><h1>Basın Bileti Talepleri</h1>';
		if (!self::ready()) echo '<div class="notice notice-error"><p>Protokol / Ücretsiz Bilet modülü (Code Snippets #127) etkin değil; onay verilemez.</p></div>';
		if (!empty($_GET['msg'])) echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['msg']))) . '</p></div>';
		$url = self::form_url();
		echo '<h2>Gizli talep bağlantısı</h2><p>Bu bağlantıyı yalnızca basın kuruluşlarına e-postayla gönderin. Bağlantıyı bilmeyen sayfayı açamaz ve sayfa arama motorlarına kapalıdır.</p><p><input type="text" readonly value="' . esc_attr($url) . '" style="width:100%" onclick="this.select()"></p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Eski bağlantı çalışmayı bırakacak. Devam edilsin mi?\')"><input type="hidden" name="action" value="mdg_press_rotate">'; wp_nonce_field('mdg_press_rotate'); echo '<button class="button">Bağlantıyı yenile (eskisini iptal et)</button></form>';
		$req = array_reverse(self::all_requests(), true);
		foreach (array('pending' => 'Onay bekleyen talepler', 'error' => 'Kontrol gereken talepler', 'approved' => 'Onaylanan talepler', 'rejected' => 'Reddedilen talepler') as $state => $label) {
			$rows = array_filter($req, function ($r) use ($state) { return ($r['state'] ?? '') === $state; });
			echo '<h2>' . esc_html($label) . ' (' . count($rows) . ')</h2>';
			if (!$rows) continue;
			echo '<table class="widefat striped"><tr><th>Tarih</th><th>Kuruluş</th><th>Gösteri / seans</th><th>Kişiler</th><th>İletişim</th><th>İşlem</th></tr>';
			foreach ($rows as $r) {
				$ppl = array(); foreach ($r['people'] as $p) $ppl[] = esc_html($p['name'] . ' (' . $p['role'] . ')');
				echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i', $r['created'], new DateTimeZone('Europe/Istanbul'))) . '</td><td><b>' . esc_html($r['org']) . '</b>' . ($r['web'] ? '<br><a href="' . esc_url($r['web']) . '" target="_blank" rel="noopener">' . esc_html($r['web']) . '</a>' : '') . ($r['note'] ? '<br><small>' . esc_html($r['note']) . '</small>' : '') . '</td><td>' . esc_html($r['event_name'] . ' · ' . $r['venue']) . '<br>' . esc_html($r['time']) . '</td><td>' . implode('<br>', $ppl) . '</td><td>' . esc_html($r['email']) . '<br>' . esc_html($r['phone']) . '</td><td>';
				if (in_array($r['state'], array('pending', 'error'), true)) {
					if (!empty($r['error'])) echo '<p style="color:#b32d2e">' . esc_html($r['error']) . '</p>';
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:2px"><input type="hidden" name="action" value="mdg_press_approve"><input type="hidden" name="req" value="' . esc_attr($r['id']) . '">'; wp_nonce_field('mdg_press_approve_' . $r['id']); echo '<button class="button button-primary">Onayla ve bileti gönder</button></form>';
					echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:2px"><input type="hidden" name="action" value="mdg_press_reject"><input type="hidden" name="req" value="' . esc_attr($r['id']) . '"><input name="reason" placeholder="Red nedeni (isteğe bağlı)" maxlength="200">'; wp_nonce_field('mdg_press_reject_' . $r['id']); echo '<button class="button">Reddet</button></form>';
				} elseif ($r['state'] === 'approved' && !empty($r['batch']) && class_exists('MDG_Protocol_Tickets')) {
					echo '<a class="button" href="' . esc_url(add_query_arg(array('page' => MDG_Protocol_Tickets::PAGE, 'batch' => $r['batch']), admin_url('admin.php'))) . '">Bilet paketini aç</a>';
					if (!empty($r['mailed'])) echo '<br><small>E-posta: ' . esc_html(wp_date('d.m.Y H:i', $r['mailed'], new DateTimeZone('Europe/Istanbul'))) . '</small>';
				} elseif ($r['state'] === 'rejected' && !empty($r['reason'])) echo esc_html($r['reason']);
				echo '</td></tr>';
			}
			echo '</table>';
		}
		echo '</div>';
	}

	static function admin_rotate() {
		self::guard(); check_admin_referer('mdg_press_rotate');
		update_option(self::ACCESS_OPTION, bin2hex(random_bytes(24)), false);
		wp_safe_redirect(self::admin_url_page(array('msg' => 'Yeni bağlantı oluşturuldu; eski bağlantı artık açılmaz.'))); exit;
	}

	static function admin_reject() {
		self::guard(); $id = sanitize_key($_POST['req'] ?? ''); check_admin_referer('mdg_press_reject_' . $id);
		try {
			$r = self::load($id); if (!in_array($r['state'], array('pending', 'error'), true)) throw new Exception('Bu talep artık değiştirilemez.');
			if (!empty($r['batch'])) throw new Exception('Bu talep için bilet üretimi başladı; paketi Protokol ekranından kontrol edin.');
			$r['state'] = 'rejected'; $r['reason'] = sanitize_text_field(wp_unslash($_POST['reason'] ?? '')); $r['decided'] = time(); $r['decided_by'] = get_current_user_id(); self::save($r);
			self::mail($r['email'], 'Basın bileti talebiniz hakkında — Madagaskar Sirki', '<p>Merhaba,</p><p>' . esc_html($r['event_name'] . ' (' . $r['time'] . ')') . ' için basın bileti talebinizi bu kez karşılayamıyoruz.' . ($r['reason'] ? ' ' . esc_html($r['reason']) : '') . '</p><p>Sorularınız için WhatsApp: ' . esc_html(self::WHATSAPP) . '</p><p>Madagaskar Sirki Türkiye</p>');
			$msg = 'Talep reddedildi.';
		} catch (Throwable $e) { $msg = $e->getMessage(); }
		wp_safe_redirect(self::admin_url_page(array('msg' => $msg))); exit;
	}

	static function admin_approve() {
		self::guard(); $id = sanitize_key($_POST['req'] ?? ''); check_admin_referer('mdg_press_approve_' . $id);
		$lock = 'mdg_press_approve_lock_' . $id;
		if (!add_option($lock, time(), '', 'no')) { wp_safe_redirect(self::admin_url_page(array('msg' => 'Bu talep şu an işleniyor.'))); exit; }
		try { $msg = self::approve($id); } catch (Throwable $e) { $msg = 'Onay tamamlanamadı: ' . $e->getMessage(); } finally { delete_option($lock); }
		wp_safe_redirect(self::admin_url_page(array('msg' => $msg))); exit;
	}

	static function approve($id) {
		if (!self::ready()) throw new Exception('Protokol modülü etkin değil.');
		$r = self::load($id);
		if (!in_array($r['state'], array('pending', 'error'), true)) throw new Exception('Bu talep zaten sonuçlandı.');
		try {
			if (empty($r['batch'])) {
				$cat = MDG_Protocol_Tickets::catalogue();
				if (!isset($cat[$r['event']]['sessions'][$r['session']])) throw new Exception('Seans artık satışta değil veya başladı.');
				self::check_limit(self::all_requests(), $r['session'], $r['org_key'], $r['email'], count($r['people']), $r['id']);
				$approved = 0; foreach (self::all_requests() as $o) if (($o['state'] ?? '') === 'approved' && ($o['id'] ?? '') !== $r['id'] && ((int) $o['session'] === (int) $r['session']) && ($o['org_key'] === $r['org_key'] || strtolower($o['email']) === strtolower($r['email']))) $approved += count($o['people']);
				if ($approved + count($r['people']) > self::MAX_PER_ORG_SESSION) throw new Exception('Kuruluş sınırı aşılıyor.');
				$e = $cat[$r['event']]['event'];
				$b = self::batch_from_request($r, $cat[$r['event']]['sessions'][$r['session']], (string) $e->title, (string) $e->venue_name, get_current_user_id(), bin2hex(random_bytes(16)));
				MDG_Protocol_Tickets::save($b);
				$r['batch'] = $b['id']; $r['state'] = 'error'; $r['error'] = 'Bilet üretimi başladı.'; self::save($r);
			}
			$b = MDG_Protocol_Tickets::issue_batch($r['batch']);
			if (($b['state'] ?? '') !== 'ready') throw new Exception('Bilet paketi hazır değil.');
		} catch (Throwable $e) {
			$r['state'] = 'error'; $r['error'] = $e->getMessage(); self::save($r); throw $e;
		}
		$r['state'] = 'approved'; unset($r['error']); $r['decided'] = time(); $r['decided_by'] = get_current_user_id(); self::save($r);
		$sent = self::send_tickets($r, $b);
		if ($sent) { $r['mailed'] = time(); self::save($r); }
		return $sent ? 'Onaylandı; biletler ' . $r['email'] . ' adresine gönderildi.' : 'Onaylandı ve biletler üretildi, ancak e-posta gönderilemedi. Bağlantıyı Protokol ekranından paylaşın.';
	}

	static function send_tickets($r, $b) {
		$links = '';
		foreach ($r['people'] as $i => $p) $links .= '<li><a href="' . esc_url(MDG_Protocol_Tickets::person_url($b, $i)) . '">' . esc_html($p['name'] . ' (' . $p['role'] . ')') . ' — bileti aç</a></li>';
		$html = '<p>Merhaba,</p><p><b>' . esc_html($r['org']) . '</b> için basın bileti talebiniz onaylandı.</p>'
			. '<p><b>' . esc_html($r['event_name']) . '</b><br>' . esc_html($r['venue']) . '<br>' . esc_html($r['time']) . '</p>'
			. '<p>Kişisel QR biletler:</p><ul>' . $links . '</ul>'
			. '<p>Tüm biletleri tek PDF olarak indirmek için: <a href="' . esc_url(add_query_arg('toplu', 'pdf', MDG_Protocol_Tickets::package_url($b))) . '">PDF indir</a></p>'
			. '<p>Girişte QR kodunu gösterin. Oturma serbesttir; kapılar seanstan 30 dakika önce açılır. Bilet yalnız bu seans için geçerlidir ve başkasına devredilemez.</p>'
			. '<p>Fotoğraf, röportaj veya ek bilgi için WhatsApp: ' . esc_html(self::WHATSAPP) . '</p><p>İyi çalışmalar,<br>Madagaskar Sirki Türkiye</p>';
		return (bool) self::mail($r['email'], 'Basın biletiniz hazır — ' . $r['event_name'], $html);
	}
}
add_action('plugins_loaded', array('MDG_Press_Tickets', 'hooks'), 20);
}
