<?php
if (!defined('ABSPATH')) exit;

final class IA_Mail_Suite {

  const OPT_KEY = 'ia_mail_suite_options';
  const CAP = 'manage_options';

  public function boot(): void {
    add_action('admin_menu', [$this, 'admin_menu']);
    add_action('admin_init', [$this, 'register_settings']);

    add_filter('wp_mail_from', [$this, 'filter_mail_from']);
    add_filter('wp_mail_from_name', [$this, 'filter_mail_from_name']);
    add_action('phpmailer_init', [$this, 'phpmailer_init']);

    add_filter('retrieve_password_title', [$this, 'filter_retrieve_password_title'], 20, 2);
    add_filter('retrieve_password_message', [$this, 'filter_retrieve_password_message'], 20, 4);

    add_filter('wp_new_user_notification_email', [$this, 'filter_new_user_notification_email'], 20, 3);
    add_filter('wp_new_user_notification_email_admin', [$this, 'filter_new_user_notification_email_admin'], 20, 3);

    add_filter('password_change_email', [$this, 'filter_password_change_email'], 20, 3);
    add_filter('email_change_email', [$this, 'filter_email_change_email'], 20, 3);

    add_filter('wp_mail', [$this, 'filter_wp_mail_fallback'], 50);

    add_action('wp_ajax_ia_mail_suite_test_send', [$this, 'ajax_test_send']);
    add_action('wp_ajax_ia_mail_suite_send_user', [$this, 'ajax_send_user']);
  }

  public function defaults(): array {
    return [
      'sender' => [
        'from_name' => get_bloginfo('name') ?: 'WordPress',
        'from_email' => 'wordpress@' . (parse_url(home_url(), PHP_URL_HOST) ?: 'localhost'),
        'reply_to' => '',
        'force_content_type' => 'text/plain',
      ],
      'smtp' => [
        'enabled' => 0,
        'host' => '',
        'port' => 587,
        'encryption' => 'tls', // none|ssl|tls
        'username' => '',
        'password' => '',
        'from_force' => 0,
      ],
      'templates' => $this->default_templates(),
      'matchers' => [],
    ];
  }

  private function default_templates(): array {
    return [
      'wp_retrieve_password' => [
        'label' => 'WordPress: Password reset email',
        'enabled' => 1,
        'subject' => '[{site_name}] Password Reset',
        'body' => "Someone has requested a password reset for the following account:\n\nSite Name: {site_name}\nUsername: {user_login}\n\nIf this was a mistake, ignore this email and nothing will happen.\n\nTo reset your password, visit the following address:\n\n{reset_url}\n\nThis password reset request originated from the IP address {ip}.\n",
      ],
      'wp_new_user_admin' => [
        'label' => 'WordPress: New user admin notification',
        'enabled' => 0,
        'subject' => '[{site_name}] New User Registration: {user_login}',
        'body' => "New user registration on your site {site_name}:\n\nUsername: {user_login}\nEmail: {user_email}\n",
      ],
      'wp_new_user_user' => [
        'label' => 'WordPress: New user welcome email (user)',
        'enabled' => 0,
        'subject' => 'Welcome to {site_name}',
        'body' => "Hi {display_name},\n\nWelcome to {site_name}.\n\nYou can log in here:\n{site_url}\n",
      ],
      'wp_password_changed' => [
        'label' => 'WordPress: Password changed notification',
        'enabled' => 0,
        'subject' => '[{site_name}] Password Changed',
        'body' => "Hi {display_name},\n\nThis notice confirms that your password was changed.\n\nIf you did not perform this change, please reset your password immediately:\n{site_url}\n",
      ],
      'wp_email_changed' => [
        'label' => 'WordPress: Email address changed notification',
        'enabled' => 0,
        'subject' => '[{site_name}] Email Changed',
        'body' => "Hi {display_name},\n\nThis notice confirms that your email address was changed.\n\nIf you did not perform this change, please contact support.\n",
      ],
      'ia_verify' => [
        'label' => 'IA: Account verification email (custom)',
        'enabled' => 0,
        'subject' => '[{site_name}] Verify your email',
        'body' => "Hi {display_name},\n\nPlease verify your email by clicking:\n{verify_url}\n\nIf you didn’t create this account, ignore this email.\n",
      ],
      'ia_one_off' => [
        'label' => 'IA: One-off message (admin → user)',
        'enabled' => 1,
        'subject' => '[{site_name}] Message',
        'body' => "Hi {display_name},\n\n{message}\n\n— {site_name}\n",
      ],
    ];
  }

  public function get_opts(): array {
    $stored = get_option(self::OPT_KEY);
    $defs = $this->defaults();
    if (!is_array($stored)) return $defs;
    return $this->deep_merge($defs, $stored);
  }

  private function deep_merge(array $a, array $b): array {
    foreach ($b as $k => $v) {
      if (is_array($v) && isset($a[$k]) && is_array($a[$k])) $a[$k] = $this->deep_merge($a[$k], $v);
      else $a[$k] = $v;
    }
    return $a;
  }

  public function admin_menu(): void {
    add_options_page('IA Mail Suite','IA Mail Suite', self::CAP,'ia-mail-suite',[$this,'render_admin']);
  }

  public function register_settings(): void {
    register_setting('ia_mail_suite', self::OPT_KEY, [$this, 'sanitize_opts']);
  }

  public function sanitize_opts($input) {
    $opts = $this->get_opts();
    if (!is_array($input)) return $opts;

    $opts['sender']['from_name'] = sanitize_text_field($input['sender']['from_name'] ?? $opts['sender']['from_name']);
    $opts['sender']['from_email'] = sanitize_email($input['sender']['from_email'] ?? $opts['sender']['from_email']);
    $opts['sender']['reply_to'] = sanitize_email($input['sender']['reply_to'] ?? $opts['sender']['reply_to']);
    $ct = ($input['sender']['force_content_type'] ?? $opts['sender']['force_content_type']);
    $opts['sender']['force_content_type'] = in_array($ct, ['text/plain','text/html'], true) ? $ct : 'text/plain';

    $opts['smtp']['enabled'] = !empty($input['smtp']['enabled']) ? 1 : 0;
    $opts['smtp']['host'] = sanitize_text_field($input['smtp']['host'] ?? '');
    $opts['smtp']['port'] = intval($input['smtp']['port'] ?? 587);
    $enc = sanitize_text_field($input['smtp']['encryption'] ?? 'tls');
    $opts['smtp']['encryption'] = in_array($enc, ['none','ssl','tls'], true) ? $enc : 'tls';
    $opts['smtp']['username'] = sanitize_text_field($input['smtp']['username'] ?? '');
    $pass = (string)($input['smtp']['password'] ?? '');
    if ($pass != '') $opts['smtp']['password'] = $pass;
    $opts['smtp']['from_force'] = !empty($input['smtp']['from_force']) ? 1 : 0;

    if (isset($input['templates']) && is_array($input['templates'])) {
      foreach ($input['templates'] as $key => $tpl) {
        if (!isset($opts['templates'][$key])) continue;
        $opts['templates'][$key]['enabled'] = !empty($tpl['enabled']) ? 1 : 0;
        $opts['templates'][$key]['subject'] = sanitize_text_field($tpl['subject'] ?? $opts['templates'][$key]['subject']);
        $raw = (string)($tpl['body'] ?? $opts['templates'][$key]['body']);
        $opts['templates'][$key]['body'] = wp_kses_post($raw);
      }
    }

    return $opts;
  }

  public function render_admin(): void {
    if (!current_user_can(self::CAP)) return;
    $opts = $this->get_opts();
    $nonce = wp_create_nonce('ia_mail_suite_ajax');
    ?>
    <div class="wrap">
      <h1>IA Mail Suite</h1>
      <p>Sender identity, SMTP, template overrides, and one-off user emails. Designed to avoid modifying your stable IA build.</p>

      <form method="post" action="options.php">
        <?php settings_fields('ia_mail_suite'); ?>

        <h2>Sender</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row"><label>From name</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[sender][from_name]" type="text" class="regular-text" value="<?php echo esc_attr($opts['sender']['from_name']); ?>" />
              <p class="description">Replaces the default “WordPress” sender name.</p></td></tr>

          <tr><th scope="row"><label>From email</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[sender][from_email]" type="email" class="regular-text" value="<?php echo esc_attr($opts['sender']['from_email']); ?>" />
              <p class="description">Use a real mailbox on your sending domain for SPF/DKIM alignment.</p></td></tr>

          <tr><th scope="row"><label>Reply-To</label></th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[sender][reply_to]" type="email" class="regular-text" value="<?php echo esc_attr($opts['sender']['reply_to']); ?>" />
              <p class="description">Optional. If empty, replies go to the From address.</p></td></tr>

          <tr><th scope="row">Email format</th>
            <td><select name="<?php echo esc_attr(self::OPT_KEY); ?>[sender][force_content_type]">
              <option value="text/plain" <?php selected($opts['sender']['force_content_type'], 'text/plain'); ?>>Plain text</option>
              <option value="text/html" <?php selected($opts['sender']['force_content_type'], 'text/html'); ?>>HTML</option>
            </select></td></tr>
        </table>

        <h2>SMTP (optional)</h2>
        <table class="form-table" role="presentation">
          <tr><th scope="row">Enable SMTP</th>
            <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][enabled]" value="1" <?php checked($opts['smtp']['enabled'], 1); ?> /> Use SMTP</label></td></tr>
          <tr><th scope="row">Host</th><td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][host]" type="text" class="regular-text" value="<?php echo esc_attr($opts['smtp']['host']); ?>" /></td></tr>
          <tr><th scope="row">Port</th><td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][port]" type="number" class="small-text" value="<?php echo esc_attr($opts['smtp']['port']); ?>" /></td></tr>
          <tr><th scope="row">Encryption</th>
            <td><select name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][encryption]">
              <option value="none" <?php selected($opts['smtp']['encryption'], 'none'); ?>>None</option>
              <option value="ssl" <?php selected($opts['smtp']['encryption'], 'ssl'); ?>>SSL</option>
              <option value="tls" <?php selected($opts['smtp']['encryption'], 'tls'); ?>>TLS</option>
            </select></td></tr>
          <tr><th scope="row">Username</th><td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][username]" type="text" class="regular-text" value="<?php echo esc_attr($opts['smtp']['username']); ?>" /></td></tr>
          <tr><th scope="row">Password</th>
            <td><input name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][password]" type="password" class="regular-text" value="" autocomplete="new-password" />
              <p class="description">Leave blank to keep stored password.</p></td></tr>
          <tr><th scope="row">Force From for SMTP</th>
            <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[smtp][from_force]" value="1" <?php checked($opts['smtp']['from_force'], 1); ?> /> Force From</label></td></tr>
        </table>

        <h2>Templates</h2>
        <p class="description">
          Use placeholders in Subject/Body:
          <code>{site_name}</code> <code>{site_url}</code> <code>{user_login}</code> <code>{user_email}</code> <code>{display_name}</code>
          <code>{reset_url}</code> <code>{verify_url}</code> <code>{ip}</code> <code>{date}</code> <code>{message}</code>.
          Also supports <code>[site_name]</code> style.
        </p>

        <?php foreach ($opts['templates'] as $key => $tpl): ?>
          <div style="border:1px solid #ccd0d4; padding:12px; margin:12px 0; background:#fff;">
            <h3 style="margin:0 0 10px;"><?php echo esc_html($tpl['label']); ?> <code style="font-size:12px;"><?php echo esc_html($key); ?></code></h3>
            <p><label><input type="checkbox" name="<?php echo esc_attr(self::OPT_KEY); ?>[templates][<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked(!empty($tpl['enabled'])); ?> /> Enable override</label></p>
            <p><label>Subject<br/><input type="text" class="large-text" name="<?php echo esc_attr(self::OPT_KEY); ?>[templates][<?php echo esc_attr($key); ?>][subject]" value="<?php echo esc_attr($tpl['subject']); ?>" /></label></p>
            <p><label>Body<br/><textarea rows="8" class="large-text code" name="<?php echo esc_attr(self::OPT_KEY); ?>[templates][<?php echo esc_attr($key); ?>][body]"><?php echo esc_textarea($tpl['body']); ?></textarea></label></p>
          </div>
        <?php endforeach; ?>

        <?php submit_button('Save settings'); ?>
      </form>

      <hr />
      <h2>Tools</h2>

      <div style="display:flex; gap:24px; flex-wrap:wrap;">
        <div style="flex:1; min-width:320px; border:1px solid #ccd0d4; background:#fff; padding:12px;">
          <h3>Test send</h3>
          <p><label>To email<br/><input id="ia_mail_test_to" type="email" class="regular-text" value="<?php echo esc_attr(get_option('admin_email')); ?>" /></label></p>
          <p><label>Template<br/>
            <select id="ia_mail_test_tpl">
              <?php foreach ($opts['templates'] as $k => $t): ?>
                <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($t['label']); ?></option>
              <?php endforeach; ?>
            </select>
          </label></p>
          <p><button class="button button-primary" id="ia_mail_test_btn">Send test</button></p>
          <div id="ia_mail_test_out" style="white-space:pre-wrap;"></div>
        </div>

        <div style="flex:1; min-width:320px; border:1px solid #ccd0d4; background:#fff; padding:12px;">
          <h3>Message a user</h3>
          <p><label>User (ID or login/email)<br/><input id="ia_mail_user_q" type="text" class="regular-text" placeholder="e.g. 123 or peertube9 or user@example.com" /></label></p>
          <p><label>Subject<br/><input id="ia_mail_user_subject" type="text" class="regular-text" value="[{site_name}] Message" /></label></p>
          <p><label>Message<br/><textarea id="ia_mail_user_message" rows="6" class="large-text code" placeholder="Write your message here..."></textarea></label></p>
          <p><button class="button button-primary" id="ia_mail_user_btn">Send to user</button></p>
          <div id="ia_mail_user_out" style="white-space:pre-wrap;"></div>
        </div>
      </div>

      <script>
      (function(){
        const ajaxUrl = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
        const nonce = <?php echo json_encode($nonce); ?>;

        function post(action, payload){
          const fd = new FormData();
          fd.append('action', action);
          fd.append('_ajax_nonce', nonce);
          Object.keys(payload).forEach(k => fd.append(k, payload[k]));
          return fetch(ajaxUrl, { method:'POST', body:fd }).then(r => r.json());
        }

        const testBtn = document.getElementById('ia_mail_test_btn');
        const testOut = document.getElementById('ia_mail_test_out');
        testBtn?.addEventListener('click', function(e){
          e.preventDefault();
          testOut.textContent = 'Sending...';
          post('ia_mail_suite_test_send', {
            to: document.getElementById('ia_mail_test_to').value,
            template: document.getElementById('ia_mail_test_tpl').value
          }).then(j => {
            testOut.textContent = (j && j.ok) ? '✅ Sent' : ('❌ ' + (j && j.error ? j.error : 'Failed'));
          }).catch(err => testOut.textContent = '❌ ' + err);
        });

        const userBtn = document.getElementById('ia_mail_user_btn');
        const userOut = document.getElementById('ia_mail_user_out');
        userBtn?.addEventListener('click', function(e){
          e.preventDefault();
          userOut.textContent = 'Sending...';
          post('ia_mail_suite_send_user', {
            q: document.getElementById('ia_mail_user_q').value,
            subject: document.getElementById('ia_mail_user_subject').value,
            message: document.getElementById('ia_mail_user_message').value
          }).then(j => {
            userOut.textContent = (j && j.ok) ? ('✅ Sent to ' + j.to) : ('❌ ' + (j && j.error ? j.error : 'Failed'));
          }).catch(err => userOut.textContent = '❌ ' + err);
        });
      })();
      </script>

    </div>
    <?php
  }

  public function filter_mail_from(string $from): string {
    $o = $this->get_opts();
    $email = $o['sender']['from_email'] ?? '';
    return is_email($email) ? $email : $from;
  }

  public function filter_mail_from_name(string $name): string {
    $o = $this->get_opts();
    $n = $o['sender']['from_name'] ?? '';
    return $n !== '' ? $n : $name;
  }

  public function phpmailer_init($phpmailer): void {
    $o = $this->get_opts();
    if (empty($o['smtp']['enabled'])) return;
    $host = trim((string)($o['smtp']['host'] ?? ''));
    if ($host === '') return;

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = intval($o['smtp']['port'] ?? 587);

    $enc = (string)($o['smtp']['encryption'] ?? 'tls');
    $phpmailer->SMTPSecure = ($enc === 'none') ? '' : $enc;

    $user = (string)($o['smtp']['username'] ?? '');
    $pass = (string)($o['smtp']['password'] ?? '');
    if ($user !== '') {
      $phpmailer->SMTPAuth = true;
      $phpmailer->Username = $user;
      $phpmailer->Password = $pass;
    } else {
      $phpmailer->SMTPAuth = false;
    }

    if (!empty($o['smtp']['from_force'])) {
      $fromEmail = (string)($o['sender']['from_email'] ?? '');
      $fromName  = (string)($o['sender']['from_name'] ?? '');
      if ($fromEmail && is_email($fromEmail)) $phpmailer->setFrom($fromEmail, $fromName, false);
    }
  }

  private function template_enabled(string $key): bool {
    $o = $this->get_opts();
    return !empty($o['templates'][$key]['enabled']);
  }

  private function apply_tokens(string $text, array $ctx): string {
    $tokens = [
      '{site_name}' => get_bloginfo('name') ?: 'WordPress',
      '{site_url}' => home_url('/'),
      '{date}' => gmdate('c'),
      '{ip}' => $this->client_ip(),
    ];
    foreach (['user_login','user_email','display_name','reset_url','verify_url','message'] as $k) {
      $tokens['{'.$k.'}'] = isset($ctx[$k]) ? (string)$ctx[$k] : '';
    }

    // [token] style
    $text = preg_replace_callback('/\[(site_name|site_url|date|ip|user_login|user_email|display_name|reset_url|verify_url|message)\]/', function($m) use ($tokens){
      return $tokens['{'.$m[1].'}'] ?? '';
    }, $text);

    return strtr($text, $tokens);
  }

  private function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
      if (!empty($_SERVER[$k])) {
        $ip = (string)$_SERVER[$k];
        if ($k === 'HTTP_X_FORWARDED_FOR') $ip = trim(explode(',', $ip)[0]);
        return sanitize_text_field($ip);
      }
    }
    return '';
  }

  private function ctx_from_user($user): array {
    $ctx = ['user_login'=>'','user_email'=>'','display_name'=>'','reset_url'=>'','verify_url'=>'','message'=>''];
    if ($user instanceof WP_User) {
      $ctx['user_login'] = $user->user_login;
      $ctx['user_email'] = $user->user_email;
      $ctx['display_name'] = $user->display_name ?: $user->user_login;
    }
    return $ctx;
  }

  public function filter_retrieve_password_title(string $title, string $user_login): string {
    if (!$this->template_enabled('wp_retrieve_password')) return $title;
    $user = get_user_by('login', $user_login);
    $ctx = $this->ctx_from_user($user);
    $o = $this->get_opts();
    return $this->apply_tokens($o['templates']['wp_retrieve_password']['subject'], $ctx);
  }

  public function filter_retrieve_password_message(string $message, string $key, string $user_login, $user_data): string {
    if (!$this->template_enabled('wp_retrieve_password')) return $message;
    $user = $user_data instanceof WP_User ? $user_data : get_user_by('login', $user_login);
    $ctx = $this->ctx_from_user($user);
    $ctx['reset_url'] = home_url('/ia-reset/?key=' . rawurlencode((string)$key) . '&login=' . rawurlencode((string)$user_login));
    $o = $this->get_opts();
    return $this->apply_tokens($o['templates']['wp_retrieve_password']['body'], $ctx);
  }

  public function filter_new_user_notification_email(array $wp_email, WP_User $user, string $blogname): array {
    if (!$this->template_enabled('wp_new_user_user')) return $wp_email;
    $ctx = $this->ctx_from_user($user);
    $o = $this->get_opts();
    $wp_email['subject'] = $this->apply_tokens($o['templates']['wp_new_user_user']['subject'], $ctx);
    $wp_email['message'] = $this->apply_tokens($o['templates']['wp_new_user_user']['body'], $ctx);
    return $wp_email;
  }

  public function filter_new_user_notification_email_admin(array $wp_email, WP_User $user, string $blogname): array {
    if (!$this->template_enabled('wp_new_user_admin')) return $wp_email;
    $ctx = $this->ctx_from_user($user);
    $o = $this->get_opts();
    $wp_email['subject'] = $this->apply_tokens($o['templates']['wp_new_user_admin']['subject'], $ctx);
    $wp_email['message'] = $this->apply_tokens($o['templates']['wp_new_user_admin']['body'], $ctx);
    return $wp_email;
  }

  public function filter_password_change_email(array $email, WP_User $user, array $userdata): array {
    if (!$this->template_enabled('wp_password_changed')) return $email;
    $ctx = $this->ctx_from_user($user);
    $o = $this->get_opts();
    $email['subject'] = $this->apply_tokens($o['templates']['wp_password_changed']['subject'], $ctx);
    $email['message'] = $this->apply_tokens($o['templates']['wp_password_changed']['body'], $ctx);
    return $email;
  }

  public function filter_email_change_email(array $email, WP_User $user, array $userdata): array {
    if (!$this->template_enabled('wp_email_changed')) return $email;
    $ctx = $this->ctx_from_user($user);
    $o = $this->get_opts();
    $email['subject'] = $this->apply_tokens($o['templates']['wp_email_changed']['subject'], $ctx);
    $email['message'] = $this->apply_tokens($o['templates']['wp_email_changed']['body'], $ctx);
    return $email;
  }

  public function filter_wp_mail_fallback(array $args): array {
    // Placeholder for v0.2 matcher editor. Keeping filter in place.
    return $args;
  }

  private function content_type(): string {
    $o = $this->get_opts();
    $ct = $o['sender']['force_content_type'] ?? 'text/plain';
    return in_array($ct, ['text/plain','text/html'], true) ? $ct : 'text/plain';
  }

  public function ajax_test_send(): void {
    if (!current_user_can(self::CAP)) wp_send_json(['ok'=>false,'error'=>'forbidden'], 403);
    check_ajax_referer('ia_mail_suite_ajax');

    $to = sanitize_email((string)($_POST['to'] ?? ''));
    $tpl = sanitize_text_field((string)($_POST['template'] ?? ''));

    if (!$to || !is_email($to)) wp_send_json(['ok'=>false,'error'=>'Invalid to email']);
    $o = $this->get_opts();
    if (!isset($o['templates'][$tpl])) wp_send_json(['ok'=>false,'error'=>'Unknown template']);

    $ctx = [
      'user_login' => 'exampleuser',
      'user_email' => $to,
      'display_name' => 'Example User',
      'reset_url' => home_url('/ia-reset/?key=EXAMPLE&login=exampleuser'),
      'verify_url' => home_url('/ia-verify/EXAMPLE'),
      'message' => 'This is a test message body.',
    ];

    $subject = $this->apply_tokens($o['templates'][$tpl]['subject'], $ctx);
    $body = $this->apply_tokens($o['templates'][$tpl]['body'], $ctx);

    $headers = [];
    if (!empty($o['sender']['reply_to']) && is_email($o['sender']['reply_to'])) $headers[] = 'Reply-To: ' . $o['sender']['reply_to'];
    if ($this->content_type() === 'text/html') $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $ok = wp_mail($to, $subject, $body, $headers);
    wp_send_json(['ok'=>(bool)$ok, 'error'=>$ok?'':'wp_mail returned false']);
  }

  public function ajax_send_user(): void {
    if (!current_user_can(self::CAP)) wp_send_json(['ok'=>false,'error'=>'forbidden'], 403);
    check_ajax_referer('ia_mail_suite_ajax');

    $q = sanitize_text_field((string)($_POST['q'] ?? ''));
    $subjectIn = sanitize_text_field((string)($_POST['subject'] ?? ''));
    $messageIn = (string)($_POST['message'] ?? '');

    if ($q === '') wp_send_json(['ok'=>false,'error'=>'Provide a user id/login/email']);
    if (trim($messageIn) === '') wp_send_json(['ok'=>false,'error'=>'Message cannot be empty']);

    $user = $this->resolve_user($q);
    if (!$user) wp_send_json(['ok'=>false,'error'=>'User not found']);

    $ctx = $this->ctx_from_user($user);
    $ctx['message'] = wp_kses_post($messageIn);

    $o = $this->get_opts();
    $subject = $this->apply_tokens($subjectIn ?: $o['templates']['ia_one_off']['subject'], $ctx);
    $body = $this->apply_tokens($o['templates']['ia_one_off']['body'], $ctx);

    $headers = [];
    if (!empty($o['sender']['reply_to']) && is_email($o['sender']['reply_to'])) $headers[] = 'Reply-To: ' . $o['sender']['reply_to'];
    if ($this->content_type() === 'text/html') $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $ok = wp_mail($user->user_email, $subject, $body, $headers);
    wp_send_json(['ok'=>(bool)$ok, 'to'=>$user->user_email, 'error'=>$ok?'':'wp_mail returned false']);
  }

  private function resolve_user(string $q): ?WP_User {
    if (ctype_digit($q)) {
      $u = get_user_by('id', intval($q));
      return ($u instanceof WP_User) ? $u : null;
    }
    if (is_email($q)) {
      $u = get_user_by('email', $q);
      return ($u instanceof WP_User) ? $u : null;
    }
    $u = get_user_by('login', $q);
    return ($u instanceof WP_User) ? $u : null;
  }
}
