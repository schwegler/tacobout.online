<?php
require_once __DIR__ . '/../inc/updates.php';

class ThemeUpdatesTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void { parent::setUp(); \Brain\Monkey\setUp(); }
    protected function tearDown(): void { \Brain\Monkey\tearDown(); parent::tearDown(); }
    public function test_network_failures_and_cached_responses() {
        global $wp_version;
        $wp_version='6.8';
        $headers=['UpdateURI'=>TACOBOUT_UPDATE_URI,'Version'=>'3.8.29'];
        $cache=false;$requests=0;$ttl=0;$response=['code'=>404,'body'=>'missing'];
        \Brain\Monkey\Functions\when('get_site_transient')->alias(function($key) use (&$cache) { return $cache; });
        \Brain\Monkey\Functions\when('set_site_transient')->alias(function($key,$value,$seconds) use (&$cache,&$ttl) { $cache=$value;$ttl=$seconds; });
        \Brain\Monkey\Functions\when('wp_safe_remote_get')->alias(function($url,$args) use (&$response,&$requests) {
            ++$requests;
            $this->assertSame(TACOBOUT_UPDATE_URI.'/releases/latest/download/tacobout-update.json',$url);
            $this->assertSame(5,$args['timeout']);$this->assertSame(16384,$args['limit_response_size']);
            $this->assertArrayNotHasKey('Authorization',$args['headers']);return $response;
        });
        \Brain\Monkey\Functions\when('is_wp_error')->alias(fn($r)=>$r===null);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(fn($r)=>$r['code']);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(fn($r)=>$r['body']);
        foreach ([null,['code'=>404,'body'=>'missing'],['code'=>429,'body'=>'rate limited'],['code'=>200,'body'=>'{bad']] as $response) {
            $cache=false;
            $this->assertFalse(tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[]));
            $this->assertSame(900,$ttl);
            $before=$requests;
            $this->assertFalse(tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[]));
            $this->assertSame($before,$requests);
        }
        $cache=false;$response=['code'=>200,'body'=>json_encode($this->metadata())];
        $this->assertSame('3.9.0',tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[])['version']);
        $this->assertSame(3600,$ttl);
    }
    public function test_pre_release_failure_hides_new_release_until_cache_is_refreshed() {
        global $wp_version;
        $wp_version='6.8';
        $headers=['UpdateURI'=>TACOBOUT_UPDATE_URI,'Version'=>'3.8.29'];
        $cache=false;$requests=0;$published=false;$deletions=[];
        \Brain\Monkey\Functions\when('get_site_transient')->alias(function($key) use (&$cache) { return $cache; });
        \Brain\Monkey\Functions\when('set_site_transient')->alias(function($key,$value,$seconds) use (&$cache) { $cache=$value; });
        \Brain\Monkey\Functions\when('delete_site_transient')->alias(function($key) use (&$cache,&$deletions) { $deletions[]=$key;if ($key==='tacobout_public_release_v1') $cache=false; });
        \Brain\Monkey\Functions\when('current_user_can')->alias(fn($cap)=>$cap==='update_themes');
        \Brain\Monkey\Functions\when('wp_safe_remote_get')->alias(function() use (&$published,&$requests) { ++$requests;return ['code'=>$published?200:404,'body'=>$published?json_encode($this->metadata()):'missing']; });
        \Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_response_code')->alias(fn($r)=>$r['code']);
        \Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(fn($r)=>$r['body']);
        $this->assertFalse(tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[]));
        $published=true;
        $previous=$_GET;
        try {
            $_GET['force-check']='1';
            // Core's query parameter alone does not invalidate our cached 404.
            $this->assertFalse(tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[]));
            $this->assertSame(1,$requests);
            tacobout_refresh_theme_release();
            $this->assertSame(['tacobout_public_release_v1','update_themes'],$deletions);
            $this->assertSame('3.9.0',tacobout_check_theme_release(false,$headers,TACOBOUT_THEME_SLUG,[])['version']);
            $this->assertSame(2,$requests);
        } finally { $_GET=$previous; }
    }
    public function test_refresh_requires_explicit_request_and_theme_update_capability() {
        $previous=$_GET;$allowed=false;$deletions=[];
        \Brain\Monkey\Functions\when('current_user_can')->alias(function() use (&$allowed) { return $allowed; });
        \Brain\Monkey\Functions\when('delete_site_transient')->alias(function($key) use (&$deletions) { $deletions[]=$key; });
        try {
            unset($_GET['force-check']);$allowed=true;
            tacobout_refresh_theme_release();
            $_GET['force-check']='1';$allowed=false;
            tacobout_refresh_theme_release();
            $this->assertSame([],$deletions);
        } finally { $_GET=$previous; }
    }
    private function metadata() {
        return ['schema'=>1,'id'=>TACOBOUT_UPDATE_URI,'theme'=>TACOBOUT_THEME_SLUG,'version'=>'3.9.0','url'=>TACOBOUT_UPDATE_URI.'/releases/tag/v3.9.0','package'=>TACOBOUT_UPDATE_URI.'/releases/download/v3.9.0/theme.zip','requires'=>'6.4','requires_php'=>'8.0'];
    }
    public function test_identity_version_urls_and_compatibility() {
        global $wp_version;
        $wp_version='6.8';
        $headers=['UpdateURI'=>TACOBOUT_UPDATE_URI,'Version'=>'3.8.29'];
        $m=$this->metadata();
        $this->assertSame('3.9.0',tacobout_release_update($m,$headers,TACOBOUT_THEME_SLUG)['version']);
        foreach (['version'=>['3.8.29','3.8.28','3.9.0-beta','../3.9.0'], 'theme'=>['other'], 'id'=>['https://evil.example'], 'url'=>['https://evil.example'], 'package'=>['http://github.com/schwegler/tacobout.online/releases/download/v3.9.0/theme.zip',TACOBOUT_UPDATE_URI.'/releases/download/v3.9.0/theme.zip?token=secret','https://github.com/other/repo/releases/download/v3.9.0/theme.zip'], 'requires'=>['99.0',null], 'requires_php'=>['99.0'], 'schema'=>[2]] as $key=>$values) {
            foreach ($values as $value) {
                $bad=$m;$bad[$key]=$value;
                $this->assertFalse(tacobout_release_update($bad,$headers,TACOBOUT_THEME_SLUG),$key);
            }
        }
        $this->assertFalse(tacobout_release_update($m,$headers,'renamed'));
        $headers['UpdateURI']='https://github.com/other/repo';
        $this->assertFalse(tacobout_release_update($m,$headers,TACOBOUT_THEME_SLUG));
    }
    public function test_other_sources_and_existing_providers_are_untouched() {
        $this->assertSame(['package'=>'existing'],tacobout_check_theme_release(['package'=>'existing'],[],TACOBOUT_THEME_SLUG,[]));
        $this->assertFalse(tacobout_check_theme_release(false,[],'other',[]));
    }
}
