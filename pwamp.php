<?php
/*
Plugin Name: PWA+AMP
Plugin URI:  https://flexplat.com
Description: Converts WordPress into Progressive Web Apps and Accelerated Mobile Pages styles.
Version:     5.10.0
Author:      Rickey Gu
Author URI:  https://flexplat.com
Text Domain: pwamp
Domain Path: /languages
*/

if ( !defined('ABSPATH') )
{
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

class PWAMP
{
	private $font_server_list = array(
		'cloud.typography.com',
		'fast.fonts.net',
		'fonts.googleapis.com',
		'use.typekit.net',
		'maxcdn.bootstrapcdn.com',
		'use.fontawesome.com'
	);

	private $time_now = 0;

	private $home_url = '';
	private $theme = '';
	private $plugins = array();

	private $page_url = '';
	private $permalink = '';
	private $plugin_dir_url = '';

	private $canonical = '';
	private $amphtml = '';

	private $home_url_pattern = '';
	private $host_url = '';

	private $plugin_dir = '';
	private $plugin_dir_path = '';

	private $base_url = '';


	public function __construct()
	{
	}

	public function __destruct()
	{
	}


	private function init()
	{
		$home_url = home_url();
		$this->home_url = preg_replace('/^https?:\/\//im', 'https://', $home_url);
		$this->theme = get_option('template');
		$this->plugins = get_option('active_plugins');

		$parts = parse_url($this->home_url);
		$this->page_url = $parts['scheme'] . '://' . $parts['host'] . remove_query_arg(array('pwamp', '__amp_source_origin'));
		$this->permalink = get_option('permalink_structure');
		$this->plugin_dir_url = plugin_dir_url(__FILE__);

		$this->amphtml = add_query_arg(array('pwamp' => ''), $this->page_url);
		$this->canonical = $this->page_url;

		$this->home_url_pattern = str_replace(array('/', '.'), array('\/', '\.'), $this->home_url);
		$this->host_url = preg_replace('/^(https:\/\/.+)\/.*$/imU', '${1}', $this->home_url);

		$this->plugin_dir = preg_replace('/^' . $this->home_url_pattern . '(.+)\/$/im', '${1}', $this->plugin_dir_url);
		$this->plugin_dir_path = plugin_dir_path(__FILE__);

		$this->base_url = '';
	}

	private function divert()
	{
		if ( preg_match('/^' . $this->home_url_pattern . '\/\??manifest\.webmanifest$/im', $this->page_url) )
		{
			$name = get_bloginfo('name');
			$description = get_bloginfo('description');

			header('Content-Type: application/x-web-app-manifest+json', true);
			echo '{"name":"' . $name . ( !empty($description) ? (' -- ' . $description) : '' ) . '","short_name":"' . $name . '","start_url":"' . $this->home_url . '/?pwamp","icons":[{"src":".' . $this->plugin_dir . ( is_plugin_active('pwamp-extension/pwamp.php') && file_exists($this->plugin_dir_path . '../pwamp-extension/pwamp/manifest/mf-logo-192.png') ? '-extension' :'' ) . '/pwamp/manifest/mf-logo-192.png","sizes":"192x192","type":"image/png","purpose":"any maskable"},{"src":".' . $this->plugin_dir . ( is_plugin_active('pwamp-extension/pwamp.php') && file_exists($this->plugin_dir_path . '../pwamp-extension/pwamp/manifest/mf-logo-512.png') ? '-extension' :'' ) . '/pwamp/manifest/mf-logo-512.png","sizes":"512x512","type":"image/png"}],"theme_color":"#ffffff","background_color":"#ffffff","display":"standalone"}';

			exit();
		}
		elseif ( preg_match('/^' . $this->home_url_pattern . '\/\??pwamp-sw\.html$/im', $this->page_url) )
		{
			header('Content-Type: text/html; charset=utf-8', true);
			echo '<!doctype html>
<html>
<head>
<title>Installing service worker...</title>
<script type=\'text/javascript\'>
	var swsource = \'' . $this->home_url . '/' . ( empty($this->permalink) ? '?' : '' ) . 'pwamp-sw.js\';
	if ( \'serviceWorker\' in navigator ) {
		navigator.serviceWorker.register(swsource).then(function(reg) {
			console.log(\'ServiceWorker scope: \', reg.scope);
		}).catch(function(err) {
			console.log(\'ServiceWorker registration failed: \', err);
		});
	};
</script>
</head>
<body>
</body>
</html>';

			exit();
		}
		elseif ( preg_match('/^' . $this->home_url_pattern . '\/\??pwamp-sw\.js$/im', $this->page_url) )
		{
			header('Content-Type: application/javascript', true);
			echo 'importScripts(\'.' . $this->plugin_dir . '/pwamp/serviceworker/sw-toolbox.js\');
toolbox.router.any(/\/wp-admin\/|\/wp-json\//, toolbox.networkOnly);
toolbox.router.any(/\/admin-ajax\.php|\/wp-activate\.php|\/wp-cron\.php|\/wp-login\.php|\/wp-signup\.php/, toolbox.networkOnly);
toolbox.router.any(/&(pwamp;)?preview=true$/, toolbox.networkOnly);
toolbox.router.default = toolbox.cacheFirst;';

			exit();
		}
	}


	private function transcode_page_desktop($page)
	{
		$head = '<link rel="amphtml" href="' . $this->amphtml . '" />
<script type="text/javascript">
	if ( screen.width <= 768 || screen.height <= 576 ) {
		window.location = "' . $this->amphtml . '";
	}
</script>' . "\n";
		$page = preg_replace('/<\/head>/i', $head . "\n" . '</head>', $page, 1);

		return $page;
	}

	private function catch_page_callback_desktop($page)
	{
		if ( empty($page) )
		{
			return;
		}

		if ( preg_match('/<form\b[^>]* method=(("post")|(\'post\'))[^>]*>/i', $page) )
		{
			return $page;
		}

		$page2 = $this->transcode_page_desktop($page);
		if ( empty($page2) )
		{
			return $page;
		}

		return $page2;
	}

	public function after_setup_theme_desktop()
	{
		ob_start(array($this, 'catch_page_callback_desktop'));
	}

	public function shutdown_desktop()
	{
		ob_end_flush();
	}


	private function echo_redirection()
	{
		require_once $this->plugin_dir_path . '../pwamp-redirection/pwamp/redirection.php';

		$redirection = new PWAMPRedirection();

		$page_path = preg_replace('/^' . $this->home_url_pattern . '(.+)$/im', '${1}', $this->page_url);

		$redirection->echo_redirection($page_path, $this->home_url);
	}

	private function get_external_canonical()
	{
		require_once $this->plugin_dir_path . '../pwamp-canonical/pwamp/canonical.php';

		$external = new PWAMPCanonical();

		$page_path = preg_replace('/^' . $this->home_url_pattern . '(.+)$/im', '${1}', $this->page_url);

		$canonical = $external->get_canonical($page_path);

		return $canonical;
	}


	private function update_url($url, $base_url = '')
	{
		if ( empty($base_url) )
		{
			$base_url = $this->home_url . '/';
		}

		if ( preg_match('/^https?:\/\/127\.0\.0\.1\/.+\//imU', $url) )
		{
			$url = preg_replace('/^https?:\/\/127\.0\.0\.1\/.+\//imU', $this->home_url . '/', $url);
		}
		elseif ( preg_match('/^https?:\/\//im', $url) )
		{
			$url = preg_replace('/^https?:\/\//im', 'https://', $url);
		}
		elseif ( preg_match('/^\/\//im', $url) )
		{
			$url = 'https:' . $url;
		}
		elseif ( preg_match('/^\//im', $url) )
		{
			$url = $this->host_url . $url;
		}
		elseif ( preg_match('/^\.\.\/\.\.\/\.\.\/\.\.\//im', $url) )
		{
			$base_url = preg_replace('/[^\/]+\/[^\/]+\/[^\/]+\/[^\/]+\/[^\/]*$/im', '', $base_url);

			$url = preg_replace('/^\.\.\/\.\.\/\.\.\/\.\.\//im', '', $url);
			$url = $base_url . $url;
		}
		elseif ( preg_match('/^\.\.\/\.\.\/\.\.\//im', $url) )
		{
			$base_url = preg_replace('/[^\/]+\/[^\/]+\/[^\/]+\/[^\/]*$/im', '', $base_url);

			$url = preg_replace('/^\.\.\/\.\.\/\.\.\//im', '', $url);
			$url = $base_url . $url;
		}
		elseif ( preg_match('/^\.\.\/\.\.\//im', $url) )
		{
			$base_url = preg_replace('/[^\/]+\/[^\/]+\/[^\/]*$/im', '', $base_url);

			$url = preg_replace('/^\.\.\/\.\.\//im', '', $url);
			$url = $base_url . $url;
		}
		elseif ( preg_match('/^\.\.\//im', $url) )
		{
			$base_url = preg_replace('/[^\/]+\/[^\/]*$/im', '', $base_url);

			$url = preg_replace('/^\.\.\//im', '', $url);
			$url = $base_url . $url;
		}
		elseif ( preg_match('/^\.\//im', $url) )
		{
			$base_url = preg_replace('/[^\/]*$/im', '', $base_url);

			$url = preg_replace('/^\.\//im', '', $url);
			$url = $base_url . $url;
		}
		else
		{
			$base_url = preg_replace('/[^\/]*$/im', '', $base_url);

			$url = $base_url . $url;
		}

		$url = htmlspecialchars_decode($url);

		return $url;
	}

	private function url_callback($matches)
	{
		if ( !empty($matches[2]) )
		{
			$match = $matches[3];
		}
		elseif ( !empty($matches[4]) )
		{
			$match = $matches[5];
		}
		else
		{
			$match = $matches[6];
		}

		if ( !preg_match('/^data\:((application)|(image))\//im', $match) )
		{
			$match = $this->update_url($match, $this->base_url);
		}

		if ( !empty($matches[2]) )
		{
			$match = '"' . $match . '"';
		}
		elseif ( !empty($matches[4]) )
		{
			$match = '\'' . $match . '\'';
		}

		return 'url(' . $match . ')';
	}

	private function external_css_callback($matches)
	{
		$match = $matches[1];

		if ( !preg_match('/ rel=(("stylesheet")|(\'stylesheet\'))/i', $match) )
		{
			return '<link' . $match . ' />';
		}


		$match = preg_replace('/ href=(((")\/\/([^"]*)("))|((\')\/\/([^\']*)(\')))/i', ' href=${3}${7}https://${4}${8}${5}${9}', $match);

		if ( !preg_match('/ href=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
		{
			return '<link' . $match . ' />';
		}
		$url = !empty($match2[2]) ? $match2[3] : $match2[5];

		$host = preg_replace('/^https?:\/\/(.+)\/.*$/imU', '${1}', $url);
		if ( in_array($host, $this->font_server_list) )
		{
			return '<link' . $match . ' />';
		}


		require_once $this->plugin_dir_path . 'pwamp/lib/get-remote-file-content.php';

		$url = $this->update_url($url);
		$css = get_remote_data($url);

		$this->base_url = $url;
		$css = preg_replace_callback('/url\((("([^"]*)")|(\'([^\']*)\')|([^"\'\)]*))\)/i', array($this, 'url_callback'), $css);

		if ( preg_match('/ media=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
		{
			$media = !empty($match2[2]) ? $match2[3] : $match2[5];
			if ( !preg_match('/^all$/im', $media ) )
			{
				$media = preg_replace('/^only screen and \(([^\)]*)\)$/im', '(${1})', $media);
				$css = '@media ' . $media . '{' . $css . '}';
			}
		}


		if ( preg_match('/ id=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
		{
			$id = !empty($match2[2]) ? $match2[3] : $match2[5];

			return '<style id="' . $id . '" type="text/css">' . $css . '</style>';
		}
		else
		{
			return '<style type="text/css">' . $css . '</style>';
		}
	}

	public function start_buffer()
	{
		ob_start();
	}

	public function end_buffer()
	{
		$buffer = ob_get_clean();

		$buffer = preg_replace_callback('/<link\b([^>]*)\s*?\/?>/iU', array($this, 'external_css_callback'), $buffer);

		echo $buffer;
	}


	private function get_page_type()
	{
		global $wp_query;

		$page_type = '';
		if ( $wp_query->is_page )
		{
			$page_type = is_front_page() ? 'front' : 'page';
		}
		elseif ( $wp_query->is_home )
		{
			$page_type = 'home';
		}
		elseif ( $wp_query->is_single )
		{
			$page_type = ( $wp_query->is_attachment ) ? 'attachment' : 'single';
		}
		elseif ( $wp_query->is_category )
		{
			$page_type = 'category';
		}
		elseif ( $wp_query->is_tag )
		{
			$page_type = 'tag';
		}
		elseif ( $wp_query->is_tax )
		{
			$page_type = 'tax';
		}
		elseif ( $wp_query->is_archive )
		{
			if ( $wp_query->is_day )
			{
				$page_type = 'day';
			}
			elseif ( $wp_query->is_month )
			{
				$page_type = 'month';
			}
			elseif ( $wp_query->is_year )
			{
				$page_type = 'year';
			}
			elseif ( $wp_query->is_author )
			{
				$page_type = 'author';
			}
			else
			{
				$page_type = 'archive';
			}
		}
		elseif ( $wp_query->is_search )
		{
			$page_type = 'search';
		}
		elseif ( $wp_query->is_404 )
		{
			$page_type = 'notfound';
		}

		return $page_type;
	}

	private function transcode_page($page)
	{
		if ( is_plugin_active('pwamp-online/pwamp.php') )
		{
			require_once $this->plugin_dir_path . '../pwamp-online/pwamp/conversion.php';
		}
		else
		{
			require_once $this->plugin_dir_path . 'pwamp/conversion.php';
		}

		$conversion = new PWAMPConversion();


		$page = preg_replace('/^[\s\t]*<style type="[^"]+" id="[^"]+"><\/style>$/im', '', $page);

		$data = array(
			'page_url' => $this->page_url,
			'canonical' => $this->canonical,
			'permalink' => $this->permalink,
			'page_type' => $this->get_page_type(),
			'plugin_dir_url' => $this->plugin_dir_url
		);


		$style = '';
		$templates = '';

		if ( is_plugin_active('pwamp-extension/pwamp.php') && file_exists($this->plugin_dir_path . '../pwamp-extension/pwamp/cfg/cfg.php') )
		{
			require_once $this->plugin_dir_path . '../pwamp-extension/pwamp/cfg/cfg.php';

			if ( defined('PWAMP_STYLE') )
			{
				$style = PWAMP_STYLE;
			}

			if ( defined('PWAMP_TEMPLATES') )
			{
				$templates = PWAMP_TEMPLATES;
			}
		}


		$page = $conversion->convert($page, $this->home_url, $data, $this->theme, $this->plugins, $style, $templates);

		return $page;
	}

	private function catch_page_callback($page)
	{
		if ( empty($page) )
		{
			return;
		}

		if ( preg_match('/<form\b[^>]* method=(("post")|(\'post\'))[^>]*>/i', $page) )
		{
			return $page;
		}

		$page2 = $this->transcode_page($page);
		if ( empty($page2) )
		{
			return $page;
		}

		return $page2;
	}

	public function after_setup_theme()
	{
		if ( empty($_COOKIE['pwamp_message']) )
		{
			ob_start(array($this, 'catch_page_callback'));

			return;
		}


		$message = $_COOKIE['pwamp_message'];
		setcookie('pwamp_message', '', $this->time_now - 1, COOKIEPATH, COOKIE_DOMAIN);

		$title = '';
		if ( !empty($_COOKIE['pwamp_title']) )
		{
			$title = $_COOKIE['pwamp_title'];
			setcookie('pwamp_title', '', $this->time_now - 1, COOKIEPATH, COOKIE_DOMAIN);
		}

		$args = array();
		if ( !empty($_COOKIE['pwamp_args']) )
		{
			$args = json_decode(stripslashes($_COOKIE['pwamp_args']));
			setcookie('pwamp_args', '', $this->time_now - 1, COOKIEPATH, COOKIE_DOMAIN);
		}

		_default_wp_die_handler($message, $title, $args);
	}

	public function shutdown()
	{
		ob_end_flush();
	}


	private function json_redirect($redirection)
	{
		$redirection = preg_replace('/^' . $this->home_url_pattern . '\//im', '', $redirection);
		if ( !preg_match('/^https?:\/\//im', $redirection) )
		{
			$redirection = $this->home_url . '/' . $redirection;
		}

		header('Content-type: application/json');
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Origin: *.ampproject.org');
		header('Access-Control-Expose-Headers: AMP-Redirect-To, AMP-Access-Control-Allow-Source-Origin');
		header('AMP-Access-Control-Allow-Source-Origin: ' . $this->host_url);
		header('AMP-Redirect-To: ' . $redirection);

		$output = [];
		echo json_encode($output);

		exit();
	}

	public function comment_post_redirect($location, $comment)
	{
		$status = 302;

		$location = wp_sanitize_redirect($location);
		$location = wp_validate_redirect($location, apply_filters('wp_safe_redirect_fallback', admin_url(), $status));

		$location = apply_filters('wp_redirect', $location, $status);
		$status = apply_filters('wp_redirect_status', $status, $location);

		$this->json_redirect($location);
	}

	public function die_handler($message, $title = '', $args = array())
	{
		if ( $title !== 'Comment Submission Failure' )
		{
			_default_wp_die_handler($message, $title, $args);

			return;
		}


		setcookie('pwamp_message', $message, $this->time_now + 5, COOKIEPATH, COOKIE_DOMAIN);

		if ( !empty($title) )
		{
			setcookie('pwamp_title', $title, $this->time_now + 5, COOKIEPATH, COOKIE_DOMAIN);
		}

		if ( !empty($args) )
		{
			setcookie('pwamp_args', json_encode($args), $this->time_now + 5, COOKIEPATH, COOKIE_DOMAIN);
		}

		$this->json_redirect($this->home_url);
	}

	public function wp_die_json_handler($function)
	{
		return array($this, 'die_handler');
	}


	public function plugins_loaded()
	{
		$this->time_now = time();

		if ( is_embed() || is_feed() )
		{
			return;
		}
		elseif ( $GLOBALS['pagenow'] === 'admin-ajax.php' || $GLOBALS['pagenow'] === 'wp-activate.php' || $GLOBALS['pagenow'] === 'wp-cron.php' || $GLOBALS['pagenow'] === 'wp-signup.php' )
		{
			return;
		}
		elseif ( is_admin() )
		{
			setcookie('pwamp_admin', '1', $this->time_now + 60*60*24, COOKIEPATH, COOKIE_DOMAIN);

			return;
		}
		elseif ( $GLOBALS['pagenow'] === 'wp-login.php' )
		{
			setcookie('pwamp_admin', '', $this->time_now - 1, COOKIEPATH, COOKIE_DOMAIN);

			return;
		}
		elseif ( !empty($_COOKIE['pwamp_admin']) )
		{
			setcookie('pwamp_admin', '1', $this->time_now + 60*60*24, COOKIEPATH, COOKIE_DOMAIN);

			return;
		}


		$this->init();

		$this->divert();


		if ( isset($_GET['pwamp']) )
		{
			$device = 'mobile';
		}
		elseif ( is_plugin_active('pwamp-canonical/pwamp.php') )
		{
			$device = 'mobile';
		}
		else
		{
			$device = 'desktop';
		}


		if ( $device == 'desktop' )
		{
			add_action('after_setup_theme', array($this, 'after_setup_theme_desktop'));
			add_action('shutdown', array($this, 'shutdown_desktop'));

			return;
		}


		if ( is_plugin_active('pwamp-redirection/pwamp.php') )
		{
			$this->echo_redirection();
		}

		if ( is_plugin_active('pwamp-canonical/pwamp.php') )
		{
			$this->canonical = $this->get_external_canonical();
		}

		if ( is_plugin_active('pwamp-extension/pwamp.php') && file_exists($this->plugin_dir_path . '../pwamp-extension/pwamp/manifest/mf-logo-192.png') )
		{
			$this->plugin_dir_url = preg_replace('/\/$/im', '', $this->plugin_dir_url) . '-extension/';
		}


		add_action('wp_head', array($this, 'start_buffer'), 0);
		add_action('wp_head', array($this, 'end_buffer'), PHP_INT_MAX);

		add_action('wp_footer', array($this, 'start_buffer'), 0);
		add_action('wp_footer', array($this, 'end_buffer'), PHP_INT_MAX);

		add_action('after_setup_theme', array($this, 'after_setup_theme'));
		add_action('shutdown', array($this, 'shutdown'));

		add_filter('comment_post_redirect', array($this, 'comment_post_redirect'), 10, 2);
		add_filter('wp_die_json_handler', array($this, 'wp_die_json_handler'), 10, 1);

		add_filter('show_admin_bar', '__return_false');


		if ( !function_exists('is_amp_endpoint') )
		{
			require_once $this->plugin_dir_path . 'pwamp/lib/amp.php';
		}
	}
}


$pwamp = new PWAMP();

add_action('plugins_loaded', array($pwamp, 'plugins_loaded'));
