<?php
/**
 * Plugin Name: SaaS Demo Block
 * Description: A modern, production-ready demo plugin showcasing a custom Gutenberg block, REST API integration with caching, a Settings page, and a WP-CLI command.
 * Version: 1.1.0
 * Author: SebWpSoft
 * License: GPLv2 or later
 * Text Domain: saas-demo-block
 */



if (!defined('ABSPATH')) exit;

final class SDB_Plugin {
    const OPT_KEY = 'sdb_options';
    const REST_NS = 'sdb/v1';

    public function __construct() {
        // 1) Registra lo script dell’editor usato nel block.json ("editorScript": "sdb-editor")
        add_action('init', [$this, 'register_editor_script']);

        // 2) Registra il blocco leggendo il block.json (richiede che lo script sia già registrato)
        add_action('init', [$this, 'register_block']);

        // 3) REST API: le rotte vanno registrate su rest_api_init
        add_action('rest_api_init', [$this, 'register_rest']);

        // 4) (Opzionale) Settings basilari
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menu']);
    }

    /* ---------- Editor script ---------- */
    public function register_editor_script() {
        $path = __DIR__ . '/block/index.js';
        wp_register_script(
            'sdb-editor',
            plugins_url('block/index.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor'],
            file_exists($path) ? filemtime($path) : false,
            true
        );
        // Passo al JS le info della REST route
        wp_localize_script('sdb-editor', 'SDB', [
            'pingUrl' => rest_url(self::REST_NS . '/ping'),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    /* ---------- Block ---------- */
    public function register_block() {
        register_block_type(__DIR__ . '/block', [
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    public function render_block($attrs) {
        $d = $this->defaults();
        $name     = sanitize_text_field($attrs['name']     ?? $d['name']);
        $tagline  = sanitize_text_field($attrs['tagline']  ?? $d['tagline']);
        $price    = sanitize_text_field($attrs['price']    ?? $d['price']);
        $ctaLabel = sanitize_text_field($attrs['ctaLabel'] ?? $d['ctaLabel']);
        $ctaUrl   = esc_url($attrs['ctaUrl']               ?? $d['ctaUrl']);
        $features = array_map('sanitize_text_field', (array)($attrs['features'] ?? $d['features']));

        // Appendi UTM se abilitato nelle opzioni
        $opts = get_option(self::OPT_KEY, []);
        if (!empty($opts['append_utm']) && $ctaUrl) {
            $sep   = (parse_url($ctaUrl, PHP_URL_QUERY)) ? '&' : '?';
            $ctaUrl .= $sep . 'utm_source=wp&utm_medium=block&utm_campaign=saas_demo';
        }

        ob_start(); ?>
        <div class="sdb-card">
            <div class="sdb-h">
                <h3 class="sdb-name"><?php echo esc_html($name); ?></h3>
                <?php if ($tagline): ?><p class="sdb-tag"><?php echo esc_html($tagline); ?></p><?php endif; ?>
            </div>
            <?php if ($features): ?>
                <ul class="sdb-feats">
                    <?php foreach ($features as $f): ?><li><?php echo esc_html($f); ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="sdb-f">
                <?php if ($price): ?><span class="sdb-price"><?php echo esc_html($price); ?></span><?php endif; ?>
                <?php if ($ctaUrl && $ctaLabel): ?>
                    <a class="sdb-cta" href="<?php echo $ctaUrl; ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html($ctaLabel); ?></a>
                <?php endif; ?>
            </div>
            <style>
                .sdb-card{border:1px solid #e2e8f0;border-radius:12px;padding:16px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.05);max-width:560px}
                .sdb-name{margin:0 0 6px;font-size:1.25rem}
                .sdb-tag{margin:0;color:#475569}
                .sdb-feats{margin:12px 0 0;padding-left:18px}
                .sdb-price{font-weight:600;margin-right:12px}
                .sdb-cta{display:inline-block;padding:8px 12px;border-radius:8px;background:#2271b1;color:#fff;text-decoration:none}
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    private function defaults() {
        $o = get_option(self::OPT_KEY, []);
        return [
            'name'     => $o['def_name']      ?? 'Acme Cloud',
            'tagline'  => $o['def_tagline']   ?? 'Your simple and scalable SaaS platform.',
            'price'    => $o['def_price']     ?? '$19/month',
            'ctaLabel' => $o['def_cta_label'] ?? 'Start Free Trial',
            'ctaUrl'   => $o['def_cta_url']   ?? 'https://example.com/signup',
            'features' => $o['def_features']  ?? ['Fast onboarding','API integration','24/7 support'],
        ];
    }

    /* ---------- REST API ---------- */
    public function register_rest() {
        register_rest_route(self::REST_NS, '/ping', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_ping'],
            'permission_callback' => '__return_true',
            'args' => [
                'url' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    public function rest_ping(WP_REST_Request $req) {
        $url = esc_url_raw($req->get_param('url'));
        if (!$url || !preg_match('#^https?://#i', $url)) {
            return new WP_REST_Response(['ok'=>false,'status'=>'invalid_url'], 400);
        }
        $key = 'sdb_ping_' . md5($url);
        if ($cached = get_transient($key)) {
            return new WP_REST_Response($cached, 200);
        }
        $resp = wp_remote_head($url, ['timeout'=>6,'redirection'=>3,'user-agent'=>'SaaS Demo Ping/1.0 (+wordpress)']);
        if (is_wp_error($resp)) {
            $data = ['ok'=>false,'status'=>'offline','error'=>$resp->get_error_message()];
            set_transient($key, $data, 60);
            return new WP_REST_Response($data, 200);
        }
        $code = wp_remote_retrieve_response_code($resp);
        $ok   = $code >= 200 && $code < 400;
        $data = ['ok'=>$ok,'status'=>$ok?'online':'offline','code'=>$code];
        set_transient($key, $data, 300);
        return new WP_REST_Response($data, 200);
    }

    /* ---------- Settings (basilari) ---------- */
    public function register_settings() {
        register_setting('sdb_settings', self::OPT_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_opts'],
        ]);

        add_settings_section('sdb_main', __('Defaults','saas-demo-block'), function () {
            echo '<p>' . esc_html__('Default values used by the block (can be overridden per-block).', 'saas-demo-block') . '</p>';
        }, 'sdb');

        $fields = [
            'def_name'      => 'Default Name',
            'def_tagline'   => 'Default Tagline',
            'def_price'     => 'Default Price',
            'def_cta_label' => 'Default CTA Label',
            'def_cta_url'   => 'Default CTA URL',
            'def_features'  => 'Default Features (one per line)',
            'append_utm'    => 'Append UTM tracking to CTA URL',
        ];
        foreach ($fields as $key => $label) {
            add_settings_field($key, esc_html__($label, 'saas-demo-block'), [$this, 'field_cb'], 'sdb', 'sdb_main', ['key'=>$key]);
        }
    }

    public function sanitize_opts($opts) {
        $c = [];
        $c['def_name']      = sanitize_text_field($opts['def_name'] ?? '');
        $c['def_tagline']   = sanitize_text_field($opts['def_tagline'] ?? '');
        $c['def_price']     = sanitize_text_field($opts['def_price'] ?? '');
        $c['def_cta_label'] = sanitize_text_field($opts['def_cta_label'] ?? '');
        $c['def_cta_url']   = esc_url_raw($opts['def_cta_url'] ?? '');
        $feats = isset($opts['def_features']) ? (string)$opts['def_features'] : '';
        $c['def_features']  = array_filter(array_map('sanitize_text_field', preg_split('/\r?\n+/', $feats)));
        $c['append_utm']    = !empty($opts['append_utm']) ? 1 : 0;
        return $c;
    }

    public function field_cb($args) {
        $key  = $args['key'];
        $opts = get_option(self::OPT_KEY, []);
        if ($key === 'def_features') {
            $val = implode("\n", (array)($opts[$key] ?? []));
            echo '<textarea name="' . esc_attr(self::OPT_KEY . '['.$key.']') . '" rows="5" cols="50">' . esc_textarea($val) . '</textarea>';
        } elseif ($key === 'append_utm') {
            $checked = !empty($opts[$key]) ? 'checked' : '';
            echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_KEY . '['.$key.']') . '" value="1" ' . $checked . '> ' . esc_html__('Enable', 'saas-demo-block') . '</label>';
        } else {
            $val = $opts[$key] ?? '';
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPT_KEY . '['.$key.']') . '" value="' . esc_attr($val) . '">';
        }
    }

    public function admin_menu() {
        add_options_page('SaaS Demo', 'SaaS Demo', 'manage_options', 'sdb', [$this, 'render_settings']);
    }

    public function render_settings() {
        echo '<div class="wrap"><h1>SaaS Demo – Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('sdb_settings');
        do_settings_sections('sdb');
        submit_button();
        echo '</form></div>';
    }
}

new SDB_Plugin();
