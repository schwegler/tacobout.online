<?php
/** Read-only contract test against an unpacked WordPress source tree; no WP bootstrap or writes.
 * Usage: php tests/native-theme-update-route.php /path/to/wordpress
 */
$source = $argv[1] ?? '';
if (!is_file($source . '/wp-admin/includes/ajax-actions.php')) { fwrite(STDERR,"Provide WordPress source directory\n");exit(2); }
const WP_CONTENT_DIR = '/not-a-real-wordpress-install';
const ABSPATH = '/not-a-real-wordpress-install/';
function __($text) { return $text; }
function wp_get_wp_version() { return '6.8'; }
function wp_parse_args($args, $defaults) { return array_merge($defaults,$args); }
function add_filter(...$args) {}
function remove_filter(...$args) {}
function add_action(...$args) {}
function remove_action(...$args) {}
function do_action(...$args) {}
function is_multisite() { return false; }
function get_stylesheet() { return 'tacobout.online-main'; }
function get_template() { return get_stylesheet(); }
function get_theme_root($theme) { return WP_CONTENT_DIR.'/themes'; }
function wp_get_theme($theme) { return new class { public function cache_delete() {} }; }
function wp_clean_themes_cache(...$args) {}
function get_option(...$args) { return []; }
function update_option(...$args) {}
function is_wp_error($value) { return false; }
function get_site_transient($key) { return (object)['response'=>['tacobout.online-main'=>['package'=>'https://github.com/schwegler/tacobout.online/releases/download/v3.8.30/theme.zip','new_version'=>'3.8.30']]]; }
class WP_Upgrader {
    public $skin, $strings=[], $bulk, $update_count, $update_current;
    public function __construct() { $this->skin=new class {
        public $theme_info;
        public function __call($method,$args) {}
    }; }
    public function init() {}
    public function fs_connect($paths) { return true; }
    public function maintenance_mode($enabled) {}
    public function run($args) { throw new RuntimeException(json_encode($args)); }
}
require $source.'/wp-admin/includes/class-theme-upgrader.php';
// Extract the actual core AJAX sanitizer rather than duplicating its expression.
$ajax=file_get_contents($source.'/wp-admin/includes/ajax-actions.php');
$start=strpos($ajax,'function wp_ajax_update_theme()');
$end=strpos($ajax,'$status', $start);
$prefix=substr($ajax,$start,$end-$start);
if (!preg_match('/\$stylesheet = (preg_replace\([^;]+);/', $prefix, $match)) { throw new RuntimeException('Core sanitizer changed; review test'); }
$_POST['slug']='tacobout.online-main';
function wp_unslash($value) { return stripslashes($value); }
$ajax_slug=eval('return '.$match[1].';');
$upgrader=new Theme_Upgrader();
$result=$upgrader->bulk_upgrade([$ajax_slug]);
if ($result !== [$ajax_slug=>true]) { throw new RuntimeException('Expected missing-key/up-to-date result'); }
echo "Actual AJAX sanitizer: tacobout.online-main -> $ajax_slug; actual Theme_Upgrader returns up_to_date without calling run().\n";
// Native bulk selection keeps the directory name. Stop exactly at the download boundary.
try {
    $upgrader->bulk_upgrade(['tacobout.online-main']);
    throw new LogicException('Expected package selection');
} catch (RuntimeException $selected) {
    $args=json_decode($selected->getMessage(),true);
    if (($args['hook_extra']['theme']??'')!=='tacobout.online-main' || ($args['package']??'')!=='https://github.com/schwegler/tacobout.online/releases/download/v3.8.30/theme.zip') { throw $selected; }
    echo "Actual Theme_Upgrader with preserved slug selects verified 3.8.30 package; stopped before download/extraction/filesystem writes.\n";
}
