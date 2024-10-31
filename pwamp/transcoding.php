<?php
if ( !defined('ABSPATH') )
{
	exit;
}

class PWAMPTranscoding
{
	private $img_style = 'amp-img.pwamp-contain>img{object-fit:contain}';
	private $sidebar_style = 'amp-sidebar>nav{width:auto;margin:0;padding:0;font-family:Lato,Arial,sans-serif;font-size:16px;line-height:1.6}amp-sidebar,amp-sidebar .submenu{width:100%;height:100%}amp-sidebar .main-menu,amp-sidebar .submenu{overflow:auto}amp-sidebar .submenu{top:0;left:0;position:absolute}amp-sidebar .hide-submenu{visibility:hidden;transform:translateX(-100%)}amp-sidebar .show-submenu{visibility:visible;transform:translateX(0)}amp-sidebar .hide-parent{visibility:hidden}amp-sidebar .truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}amp-sidebar .link-container{display:block;height:44px;line-height:44px;border-bottom:1px solid #f0f0f0;padding:0 1rem}amp-sidebar a{min-width:44px;min-height:44px;text-decoration:none;cursor:pointer}amp-sidebar .submenu-icon{padding-right:44px}amp-sidebar .submenu-icon::after{position:absolute;right:0;height:44px;width:44px;content:\'\';background-size:1rem;background-image:url(\'data:image/svg+xml;utf8, <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M5 3l3.057-3 11.943 12-11.943 12-3.057-3 9-9z"/></svg>\');background-repeat:no-repeat;background-position:center}amp-sidebar .controls{display:flex;height:50px;background:#f0f0f0}amp-sidebar .controls a{display:flex;justify-content:center;align-items:center}amp-sidebar .controls span{line-height:50px;margin:0 auto}amp-sidebar nav>.controls>a:first-of-type{visibility:hidden}amp-sidebar .controls a svg{height:1rem;width:1rem}amp-sidebar .link-icon{float:left;height:44px;margin-right:.75rem}amp-sidebar .link-icon>svg{height:44px}amp-sidebar{background:#fff;color:#232323;fill:#232323;text-transform:uppercase;letter-spacing:.18rem;font-size:.875rem}amp-sidebar a{color:#232323;text-transform:none;letter-spacing:normal}div[class*="-sidebar-mask"]{opacity:.8}amp-sidebar a:hover{text-decoration:underline;fill:#232323}amp-sidebar .view-all{font-style:italic;font-weight:700}';

	private $home_url = '';

	private $page_url = '';
	private $canonical = '';
	private $permalink = '';
	private $page_type = '';
	private $plugin_dir_url = '';

	private $home_url_pattern = '';
	private $host_url = '';

	private $style_list = array();
	private $image_list = array();
	private $selector_list = array();
	private $selector_remove_list = array();

	private $style = '';
	private $extened_style = false;

	private $base_url = '';
	private $head = '';
	private $body = '';


	public function __construct()
	{
	}

	public function __destruct()
	{
	}


	public function init($home_url, $data)
	{
		$this->home_url = preg_replace('/\/$/im', '', $home_url);


		if ( !empty($data['page_url']) && is_string($data['page_url']) )
		{
			$this->page_url = $data['page_url'];
		}
		else
		{
			$this->page_url = $home_url . '/';
		}

		if ( !empty($data['canonical']) && is_string($data['canonical']) )
		{
			$this->canonical = $data['canonical'];
		}
		else
		{
			$this->canonical = $this->page_url;
		}

		if ( !empty($data['permalink']) && is_string($data['permalink']) )
		{
			$this->permalink = $data['permalink'];
		}

		if ( !empty($data['page_type']) && is_string($data['page_type']) )
		{
			$this->page_type = $data['page_type'];
		}

		if ( !empty($data['plugin_dir_url']) && is_string($data['plugin_dir_url']) )
		{
			$this->plugin_dir_url = $data['plugin_dir_url'];
		}


		$home_url_pattern = preg_replace('/^https?:\/\/(www\.)?/im', 'https?://(www.)?', $this->home_url);
		$this->home_url_pattern = str_replace(array('/', '.'), array('\/', '\.'), $home_url_pattern);

		$this->host_url = preg_replace('/^https?:\/\/([^\/]*?)\/??.*$/imU', 'https://${1}', $this->home_url);


		$this->base_url = '';
		$this->head = '';
		$this->body = '';
	}


	public function set_style_list($style_list)
	{
		$this->style_list = $style_list;
	}

	public function set_image_list($image_list)
	{
		$this->image_list = $image_list;
	}

	public function set_selector_list($selector_list)
	{
		$this->selector_list = $selector_list;
	}

	public function set_selector_remove_list($selector_list)
	{
		$this->selector_remove_list = $selector_list;
	}

	public function get_home_url()
	{
		return $this->home_url;
	}

	public function get_home_url_pattern()
	{
		return $this->home_url_pattern;
	}


	private function minicss($css, $id = '')
	{
		$css = !empty($id) ? $id . '{' . $css . '}' : $css;

		$css = preg_replace('/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);
		$css = preg_replace('/[\s\t\r\n]+/', ' ', $css);
		$css = preg_replace('/\s*([{}\[\(:;,>\+~])\s*/i', '${1}', $css);
		$css = str_replace(';}', '}', $css);
		$css = trim($css);

		$css = preg_replace('/\s*!important\b\s*/i', '', $css);
		$css = preg_replace('/\s*@charset (("utf-8")|(\'utf-8\'));\s*/i', '', $css);
		$css = preg_replace('/\s*@((-ms-viewport)|(viewport)){[^}]+}\s*/i', '', $css);
		$css = preg_replace('/\s*text-rendering:\s*optimizeLegibility;??\s*/iU', '', $css);
		$css = preg_replace('/\s*\*display:\s*/i', 'display:', $css);

		if ( !empty($id) && preg_match('/{}$/im', $css) )
		{
			return;
		}

		return $css;
	}

	private function update_url($url, $base_url = '')
	{
		if ( empty($base_url) )
		{
			$base_url = $this->home_url . '/';
		}

		if ( preg_match('/^https?:\/\//im', $url) )
		{
			$url = preg_replace('/^http:\/\//im', 'https://', $url);
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

	private function get_extened_style()
	{
		$this->extened_style = false;

		if ( empty($this->style_list) )
		{
			return;
		}

		$url = preg_replace('/^' . $this->home_url_pattern . '\//im', '', $this->page_url);
		$url = md5($url);

		$style = '';
		if ( array_key_exists($url, $this->style_list) )
		{
			$style = $this->style_list[$url];
		}
		elseif ( array_key_exists($this->page_type, $this->style_list) )
		{
			$style = $this->style_list[$this->page_type];
		}
		else
		{
			return;
		}

		$this->base_url = $this->home_url . '/';
		$style = preg_replace_callback('/url\((("([^"]*)")|(\'([^\']*)\')|([^"\'\)]*))\)/i', array($this, 'url_callback'), $style);

		$this->extened_style = true;

		return $style;
	}

	private function collect_selector($page)
	{
		if ( $this->extened_style )
		{
			return;
		}

		preg_match_all('/<[a-z][^>]*\s+class=(("([^"]*)")|(\'([^\']*)\'))[^>]*>/i', $page, $matches);
		foreach ( $matches[1] as $key => $value )
		{
			$value = !empty($matches[2][$key]) ? $matches[3][$key] : $matches[5][$key];

			$matches2 = preg_split('/\s+/', $value, 0, PREG_SPLIT_NO_EMPTY);
			foreach ( $matches2 as $value )
			{
				$this->selector_list['.' . $value] = true;
			}
		}

		preg_match_all('/<[a-z][^>]*\s+id=(("([^"]*)")|(\'([^\']*)\'))[^>]*>/i', $page, $matches);
		foreach ( $matches[1] as $key => $value )
		{
			$value = !empty($matches[2][$key]) ? $matches[3][$key] : $matches[5][$key];

			$this->selector_list['#' . $value] = true;
		}

		preg_match_all('/<([a-z][_a-z0-9-]*)(\s+[^>]*)?>/i', $page, $matches);
		foreach ( $matches[1] as $value )
		{
			$this->selector_list[$value] = true;
		}
	}


	private function form_callback($matches)
	{
		$match = $matches[1];

		if ( preg_match('/ method=(("post")|(\'post\'))/i', $match) )
		{
			if ( !preg_match('/ action=(("[^"]*")|(\'[^\']*\'))/i', $match) )
			{
				// The mandatory attribute 'action-xhr' is missing in tag 'FORM [method=POST]'.
				$match .= ' action-xhr="' . $this->page_url . '"';
			}
			elseif ( preg_match('/ action=(("")|(\'\'))/i', $match) )
			{
				// Missing URL for attribute 'action-xhr' in tag 'form'.
				$match = preg_replace('/ action=(("[^"]*")|(\'[^\']*\'))/i', ' action-xhr="' . $this->page_url . '"', $match);
			}
			else
			{
				// The attribute 'action' may not appear in tag 'FORM [method=POST]'.
				$match = preg_replace('/ action=(("[^"]*")|(\'[^\']*\'))/i', ' action-xhr=${1}', $match);
			}

			if ( !preg_match('/ target=(("[^"]*")|(\'[^\']*\'))/i', $match) )
			{
			}
			elseif ( !preg_match('/ target=(("_blank")|(\'_blank\'))/i', $match) && !preg_match('/ target=(("_top")|(\'_top\'))/i', $match) )
			{
				// The attribute 'target' in tag 'form' is set to the invalid value 'popupwindow'.
				$match = preg_replace('/ target=(("[^"]*")|(\'[^\']*\'))/i', ' target="_top"', $match);
			}
		}
		else
		{
			if ( !preg_match('/ action=(("[^"]*")|(\'[^\']*\'))/i', $match) )
			{
				// The mandatory attribute 'action' is missing in tag 'FORM [method=GET]'.
				$match .= ' action="' . $this->page_url . '"';
			}
			elseif ( preg_match('/ action=(("")|(\'\'))/i', $match) )
			{
				// Missing URL for attribute 'action' in tag 'form'.
				$match = preg_replace('/ action=(("[^"]*")|(\'[^\']*\'))/i', ' action="' . $this->page_url . '"', $match);
			}

			if ( !preg_match('/ target=(("[^"]*")|(\'[^\']*\'))/i', $match) )
			{
				// The mandatory attribute 'target' is missing in tag 'FORM [method=GET]'.
				$match .= ' target="_top"';
			}
			elseif ( !preg_match('/ target=(("_blank")|(\'_blank\'))/i', $match) && !preg_match('/ target=(("_top")|(\'_top\'))/i', $match) )
			{
				// The attribute 'target' in tag 'form' is set to the invalid value 'popupwindow'.
				$match = preg_replace('/ target=(("[^"]*")|(\'[^\']*\'))/i', ' target="_top"', $match);
			}
		}

		return '<form' . $match . '>';
	}

	private function form2_callback($matches)
	{
		$match = !empty($matches[5]) ? $matches[6] : $matches[8];

		if ( preg_match('/^' . $this->home_url_pattern . '\//im', $match) )
		{
			$match = str_replace('&#038;', '&amp;', $match);
			$match = preg_replace('/^(.*)(((\?)|(&(amp;)?))pwamp)?(#.*)?$/imU', '${1}${7}', $match);
			$match = preg_replace('/^(.*)(#.*)?$/imU', '${1}' . ( ( strpos($match, '?') !== false ) ? '&amp;' : '?' ) . 'pwamp${2}', $match);
		}

		return '<form' . $matches[1] . $matches[2] . '="' . $match . '"' . $matches[9] . '>';
	}

	private function href_callback($matches)
	{
		$match = !empty($matches[3]) ? $matches[4] : $matches[6];

		if ( preg_match('/^' . $this->home_url_pattern . '\//im', $match) )
		{
			$match = str_replace('&#038;', '&amp;', $match);
			$match = preg_replace('/^(.*)(((\?)|(&(amp;)?))pwamp)?(#.*)?$/imU', '${1}${7}', $match);
			$match = preg_replace('/^(.*)(#.*)?$/imU', '${1}' . ( ( strpos($match, '?') !== false ) ? '&amp;' : '?' ) . 'pwamp${2}', $match);
		}

		return '<a' . $matches[1] . ' href="' . $match . '"' . $matches[7] . '>';
	}

	private function iframe_callback($matches)
	{
		$match = $matches[1];

		if ( !preg_match('/ src=(("[^"]*")|(\'[^\']*\'))/i', $match) )
		{
			return '';
		}

		$match = preg_replace('/ sizes=(("[^"]*")|(\'[^\']*\'))/i', '', $match);

		// The attribute 'frameborder' in tag 'amp-iframe' is set to the invalid value 'no'.
		$match = preg_replace('/ frameborder=(((")no("))|((\')no(\')))/i', ' frameborder=${3}${6}0${4}${7}', $match);

		if ( !preg_match('/ height=(("[^"]*")|(\'[^\']*\'))/i', $match) )
		{
			$match .= ' height="400"';
		}

		$match = preg_replace('/ layout=(("[^"]*")|(\'[^\']*\'))/i', '', $match);
		if ( !preg_match('/ width=(("[^"]*")|(\'[^\']*\'))/i', $match) )
		{
			$match .= ' width="auto"';
			$match .= ' layout="fixed-height"';
		}
		else
		{
			$match .= ' layout="intrinsic"';
		}

		$match .= ' sandbox="allow-scripts allow-same-origin"';

		return '<amp-iframe' . $match . '></amp-iframe>';
	}

	private function img_callback($matches)
	{
		$match = $matches[1];

		if ( !preg_match('/ src=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
		{
			return '';
		}
		$src = !empty($match2[2]) ? $match2[3] : $match2[5];


		if ( !empty($this->image_list) )
		{
			$src = preg_replace('/.*' . $this->home_url_pattern . '\//i', '', $src);
			$img = 'src="' . $src . '"';

			if ( preg_match('/ width=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
			{
				$width = !empty($match2[2]) ? $match2[3] : $match2[5];
				$img .= ' width="' . $width . '"';
			}

			if ( preg_match('/ height=(("([^"]*)")|(\'([^\']*)\'))/i', $match, $match2) )
			{
				$height = !empty($match2[2]) ? $match2[3] : $match2[5];
				$img .= ' height="' . $height . '"';
			}

			if ( array_key_exists($img, $this->image_list) )
			{
				$match .= $this->image_list[$img];
			}
		}

		if ( !preg_match('/ width=(("([^"]*)")|(\'([^\']*)\'))/i', $match) )
		{
			$match .= ' width="400"';
		}

		if ( !preg_match('/ height=(("([^"]*)")|(\'([^\']*)\'))/i', $match) )
		{
			$match .= ' height="600"';
		}


		$match = preg_replace('/ loading=(("[^"]*")|(\'[^\']*\'))/i', '', $match);
		$match = preg_replace('/ sizes=(("[^"]*")|(\'[^\']*\'))/i', '', $match);

		if ( preg_match('/ class=(("[^"]*")|(\'[^\']*\'))/i', $match) )
		{
			$match = preg_replace('/ class=(((")([^"]*)("))|((\')([^\']*)(\')))/i', ' class=${3}${7}${4}${8} pwamp-contain${5}${9}', $match);
		}
		else
		{
			$match .= ' class="pwamp-contain"';
		}

		$match = preg_replace('/ layout=(("[^"]*")|(\'[^\']*\'))/i', '', $match);
		$match .= ' layout="intrinsic"';

		if ( empty($matches[2]) )
		{
			$matches[2] = '<noscript><img' . $matches[1] . ' /></noscript>';
		}

		return '<amp-img' . $match . '>' . $matches[2] . '</amp-img>';
	}

	private function inline_css_callback($matches)
	{
		if ( empty($matches[4]) ) $matches[4] = '';
		if ( empty($matches[6]) ) $matches[6] = '';
		if ( empty($matches[8]) ) $matches[8] = '';
		if ( empty($matches[10]) ) $matches[10] = '';

		$match = !empty($matches[3]) ? $matches[5] : $matches[9];

		$match = $this->minicss($match);

		return '<' . $matches[1] . ' style=' . $matches[4] . $matches[8] . $match . $matches[6] . $matches[10] . $matches[11] . $matches[12] . $matches[13] . '>';
	}

	private function internal_css_callback($matches)
	{
		$match = $matches[2];

		if ( !$this->extened_style )
		{
			$this->style .= $match;
		}

		return '';
	}

	private function pixel_callback($matches)
	{
		$this->body .= "\n" . '<amp-pixel src="' . $matches[1] . '" layout="nodisplay"></amp-pixel>';

		return '';
	}

	private function script_callback($matches)
	{
		if ( !empty($matches[1]) && !empty($matches[2]) )
		{
			return $matches[0];
		}

		return '';
	}

	private function textarea_callback($matches)
	{
		$match = $matches[2];

		$match = str_replace(array("\r\n", "\r", "\n"), '<amp-br />', $match);

		return '<textarea' . $matches[1] . '>' . $match . '</textarea>';
	}

	private function textarea2_callback($matches)
	{
		$match = $matches[2];

		$match = str_replace('<amp-br />', "\n", $match);

		return '<textarea' . $matches[1] . '>' . $match . '</textarea>';
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

	private function video_callback($matches)
	{
		$match = $matches[1];

		if ( !preg_match('/ width=(("([^"]*)")|(\'([^\']*)\'))/i', $match) )
		{
			$match .= ' width="400"';
		}

		if ( !preg_match('/ height=(("([^"]*)")|(\'([^\']*)\'))/i', $match) )
		{
			$match .= ' height="225"';
		}

		// The attribute 'playsinline' may not appear in tag 'amp-video'.
		$match = preg_replace('/ playsinline=(("[^"]*")|(\'[^\']*\'))/i', '', $match);

		return '<amp-video' . $match . '>' . $matches[2] . '</amp-video>';
	}

	public function transcode_html($page)
	{
		$this->style = $this->get_extened_style();


		$page = preg_replace('/<!--.*-->/isU', '', $page);


		/*
			<script></script>
		*/
		// Custom JavaScript is not allowed.
		$page = preg_replace_callback('/(<amp-animation\b[^>]*>[\s\t\r\n]*)??<script\b[^>]*>.*<\/script>([\s\t\r\n]*<\/amp-animation>)??/isU', array($this, 'script_callback'), $page);


		/*
			<style></style>
		*/
		// The mandatory attribute 'amp-custom' is missing in tag 'style amp-custom'.
		$page = preg_replace_callback('/(<noscript>[\s\t\r\n]*)??<style\b[^>]*>(.*)<\/style>([\s\t\r\n]*<\/noscript>)??/isU', array($this, 'internal_css_callback'), $page);


		/*
			<a></a>
		*/
		$page = preg_replace_callback('/<a\b([^>]*) href=(("([^"]*)")|(\'([^\']*)\'))([^>]*)\s*?>/iU', array($this, 'href_callback'), $page);


		/*
			<audio></audio>
		*/
		// The tag 'audio' may only appear as a descendant of tag 'noscript'. Did you mean 'amp-audio'?
		$page = preg_replace('/<audio\b([^>]*)\s*?>/iU', '<amp-audio${1}>', $page);


		/*
			<form></form>
		*/
		$page = preg_replace_callback('/<form\b([^>]*)\s*?>/iU', array($this, 'form_callback'), $page);
		$page = preg_replace_callback('/<form\b([^>]*)( action(-xhr)?)=(("([^"]*)")|(\'([^\']*)\'))([^>]*)\s*?>/iU', array($this, 'form2_callback'), $page);


		/*
			<head></head>
		*/
		$page = preg_replace('/^[\s\t]*<head>/im', '<head>', $page, 1);
		$page = preg_replace('/^[\s\t]*<\/head>/im', '</head>', $page, 1);


		/*
			<html>
		*/
		$page = preg_replace('/<html\b([^>]*)\s*?>/iU', '<html amp${1}>', $page, 1);


		/*
			<iframe></iframe>
		*/
		// The tag 'iframe' may only appear as a descendant of tag 'noscript'. Did you mean 'amp-iframe'?
		$page = preg_replace_callback('/<iframe\b([^>]*)\s*?\/?>\s*<\/iframe>/iU', array($this, 'iframe_callback'), $page);


		/*
			Pixel
		*/
		$page = preg_replace_callback('/<noscript>\s*<img height="1" width="1"[^>]* src="([^"]+)"[^>]*\s*?\/?>\s*<\/noscript>/isU', array($this, 'pixel_callback'), $page);


		/*
			<img/>
		*/
		// The tag 'img' may only appear as a descendant of tag 'noscript'. Did you mean 'amp-img'?
		$page = preg_replace_callback('/<img\b([^>]*)\s*?\/?>(<noscript><img\b[^>]*\s*?\/?><\/noscript>)??/iU', array($this, 'img_callback'), $page);


		/*
			<link/>
		*/
		// The tag 'link rel=canonical' appears more than once in the document.
		$page = preg_replace('/<link\b[^>]* rel=(("canonical")|(\'canonical\'))[^>]*\s*?\/?>/iU', '', $page);

		$page = preg_replace('/^[\s\t]*<link\b([^>]*)\s*?>/imU', '<link${1}>', $page);


		/*
			<meta>
		*/
		// The tag 'meta charset=utf-8' appears more than once in the document.
		$page = preg_replace('/<meta\b[^>]* charset=(("utf-8")|(\'utf-8\'))[^>]*\s*?\/?>/iU', '', $page);

		// The tag 'meta name=viewport' appears more than once in the document.
		$page = preg_replace('/<meta\b[^>]* name=(("viewport")|(\'viewport\'))[^>]*\s*?\/?>/iU', '', $page);

		$page = preg_replace('/^[\s\t]*<meta\b([^>]*)\s*?>/imU', '<meta${1}>', $page);


		/*
			<select></select>
		*/
		// The attribute 'autocomplete' may not appear in tag 'select'.
		$page = preg_replace('/<select\b([^>]*) autocomplete=(("[^"]*")|(\'[^\']*\'))([^>]*)\s*?>/iU', '<select${1}${5}>', $page);


		/*
			<title></title>
		*/
		$page = preg_replace('/^[\s\t]*<title>(.*)<\/title>/im', '<title>${1}</title>', $page, 1);


		/*
			<video></video>
		*/
		// The tag 'video' may only appear as a descendant of tag 'noscript'. Did you mean 'amp-video'?
		$page = preg_replace_callback('/<video\b([^>]*)\s*?>(.*)<\/video>/isU', array($this, 'video_callback'), $page);


		/*
			Any Tag
		*/
		// The attribute 'onclick' may not appear in tag 'a'.
		$page = preg_replace('/<(\w+\b[^>]*) on\w+=(("[^"]*")|(\'[^\']*\'))([^>]*)\s*(\s?)(\/?)>/iU', '<${1}${5}${6}${7}>', $page);

		$page = preg_replace_callback('/<(\w+\b[^>]*) style=(((")([^"]*)("))|((\')([^\']*)(\')))([^>]*)\s*(\s?)(\/?)>/iU', array($this, 'inline_css_callback'), $page);

		return $page;
	}


	private function link_callback($matches)
	{
		if ( preg_match('/ href=(("(https:)?\/\/cdn\.ampproject\.org\/?")|(\'(https:)?\/\/cdn\.ampproject\.org\/?\'))/i', $matches[1]) )
		{
			return '';
		}
		elseif ( preg_match('/ href=(("(https:)?\/\/cdn\.ampproject\.org\/?")|(\'(https:)?\/\/cdn\.ampproject\.org\/?\'))/i', $matches[17]) )
		{
			return '';
		}
		elseif ( preg_match('/ href=(("(https:)?\/\/cdn\.ampproject\.org\/v0\.js")|(\'(https:)?\/\/cdn\.ampproject\.org\/v0\.js\'))/i', $matches[1]) )
		{
			return '';
		}
		elseif ( preg_match('/ href=(("(https:)?\/\/cdn\.ampproject\.org\/v0\.js")|(\'(https:)?\/\/cdn\.ampproject\.org\/v0\.js\'))/i', $matches[17]) )
		{
			return '';
		}
		elseif ( preg_match('/ href=(("(https:)?\/\/s\.w\.org\/?")|(\'(https:)?\/\/s\.w\.org\/?\'))/i', $matches[1]) )
		{
			return '';
		}
		elseif ( preg_match('/ href=(("(https:)?\/\/s\.w\.org\/?")|(\'(https:)?\/\/s\.w\.org\/?\'))/i', $matches[17]) )
		{
			return '';
		}

		$this->head .= "\n" . '<link' . $matches[1] . ' rel=' . $matches[2] . $matches[17] . ' />';

		return '';
	}

	private function link2_callback($matches)
	{
		$this->head .= '<link' . $matches[1] . ' rel=' . $matches[2] . $matches[5] . ' />'. "\n";

		return '';
	}

	private function title_callback($matches)
	{
		$this->head .= "\n" . '<title>' . $matches[1] . '</title>';

		return '';
	}

	public function transcode_head($page)
	{
		// Service Workers
		$this->body = '<amp-install-serviceworker src="' . $this->home_url . '/' . ( empty($this->permalink) ? '?' : '' ) . 'pwamp-sw.js" data-iframe-src="' . $this->home_url . '/' . ( empty($this->permalink) ? '?' : '' ) . 'pwamp-sw.html" layout="nodisplay"></amp-install-serviceworker>';

		$page = preg_replace('/<body\b([^>]*)\s*?>/iU', '<body${1}>' . "\n" . $this->body, $page, 1);


		$this->style = $this->minicss($this->style);

		$this->style = preg_replace_callback('/@media\b([^{]*)({((?:[^{}]+|(?2))*)})/i', array($this, 'media_callback'), $this->style);

		$this->style = preg_replace('/@-moz-document\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@-moz-keyframes\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@-ms-keyframes\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@-o-keyframes\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@-webkit-keyframes\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@font-face{(.+),url\("?data:application\/x-font-woff;charset=utf-8;base64,.+="?\)(.+)}/iU', '@font-face{${1}${2}}', $this->style);
		$this->style = preg_replace('/@keyframes\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);
		$this->style = preg_replace('/@supports\b[^{]*({((?:[^{}]+|(?1))*)})/i', '', $this->style);

		$this->collect_selector($page);
		$this->style = preg_replace_callback('/([^{]+)({((?:[^{}]+|(?2))*)})/i', array($this, 'css_callback'), $this->style);

		if ( preg_match('/<amp-img\b[^>]*>/i', $page) )
		{
			$this->style .= $this->img_style;
		}

		if ( preg_match('/<amp-sidebar\b[^>]*>/i', $page) )
		{
			$this->style .= $this->sidebar_style;
		}


		// The mandatory tag 'meta charset=utf-8' is missing or incorrect.
		$this->head = '<meta charset="utf-8" />';

		// The mandatory tag 'meta name=viewport' is missing or incorrect.
		$this->head .= "\n" . '<meta name="viewport" content="width=device-width, minimum-scale=1, initial-scale=1" />';

		// pwamp-page-type
		if ( !empty($this->page_type) )
		{
			$this->head .= "\n" . '<meta name="pwamp-page-type" content="' . $this->page_type . '" />';
		}

		// Progressive Web Apps
		$this->head .= "\n" . '<meta name="theme-color" content="#ffffff" />';
		$this->head .= "\n" . '<link rel="manifest" href="' . $this->home_url . '/' . ( !empty($this->permalink) ? 'manifest.webmanifest' : '?manifest.webmanifest' ) . '" />';
		$this->head .= "\n" . '<link rel="apple-touch-icon" href="' . $this->plugin_dir_url . 'pwamp/manifest/mf-logo-192.png" />';

		$page = preg_replace_callback('/<title>(.*)<\/title>/iU', array($this, 'title_callback'), $page);

		// The mandatory tag 'amphtml engine v0.js script' is missing or incorrect.
		$this->head .= "\n" . '<link rel="preconnect" href="https://cdn.ampproject.org" />';
		$this->head .= "\n" . '<link rel="dns-prefetch" href="https://s.w.org" />';
		$this->head .= "\n" . '<link rel="preload" as="script" href="https://cdn.ampproject.org/v0.js" />';
		$this->head .= "\n" . '<script async src="https://cdn.ampproject.org/v0.js"></script>';

		$page = preg_replace_callback('/<link\b([^>]*) rel=(("((preconnect)|(dns-prefetch)|(preload)|(prerender)|(prefetch))")|(\'((preconnect)|(dns-prefetch)|(preload)|(prerender)|(prefetch))\'))([^>]*)\s*?\/?>/iU', array($this, 'link_callback'), $page);

		// The tag 'amp-accordion' requires including the 'amp-accordion' extension JavaScript.
		if ( preg_match('/<amp-accordion\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-accordion" src="https://cdn.ampproject.org/v0/amp-accordion-0.1.js"></script>';
		}

		// The tag 'amp-analytics' requires including the 'amp-analytics' extension JavaScript.
		if ( preg_match('/<amp-analytics\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>';
		}

		// The tag 'amp-animation' requires including the 'amp-animation' extension JavaScript.
		if ( preg_match('/<amp-animation\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-animation" src="https://cdn.ampproject.org/v0/amp-animation-0.1.js"></script>';
		}

		// The tag 'amp-audio' requires including the 'amp-audio' extension JavaScript.
		if ( preg_match('/<amp-audio\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-audio" src="https://cdn.ampproject.org/v0/amp-audio-0.1.js"></script>';
		}

		// The tag 'amp-state' requires including the 'amp-bind' extension JavaScript.
		if ( preg_match('/<amp-sidebar\b[^>]*>/i', $page) || preg_match('/<amp-state\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-bind" src="https://cdn.ampproject.org/v0/amp-bind-0.1.js"></script>';
		}

		// The tag 'amp-carousel' requires including the 'amp-carousel' extension JavaScript.
		if ( preg_match('/<amp-carousel\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-carousel" src="https://cdn.ampproject.org/v0/amp-carousel-0.1.js"></script>';
		}

		// The tag 'FORM [method=POST]' requires including the 'amp-form' extension JavaScript.
		// The tag 'FORM [method=GET]' requires including the 'amp-form' extension JavaScript.
		if ( preg_match('/<form\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-form" src="https://cdn.ampproject.org/v0/amp-form-0.1.js"></script>';
		}

		// The tag 'amp-iframe' requires including the 'amp-iframe' extension JavaScript.
		if ( preg_match('/<amp-iframe\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-iframe" src="https://cdn.ampproject.org/v0/amp-iframe-0.1.js"></script>';
		}

		// The tag 'amp-install-serviceworker' requires including the 'amp-install-serviceworker' extension JavaScript.
		if ( preg_match('/<amp-install-serviceworker\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-install-serviceworker" src="https://cdn.ampproject.org/v0/amp-install-serviceworker-0.1.js"></script>';
		}

		// The tag 'template' requires including the 'amp-mustache' extension JavaScript.
		if ( preg_match('/<template type="amp-mustache">/i', $page) || preg_match('/<script type="text\/plain" template="amp-mustache">/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-template="amp-mustache" src="https://cdn.ampproject.org/v0/amp-mustache-0.2.js"></script>';
		}

		// The tag 'amp-position-observer' requires including the 'amp-position-observer' extension JavaScript.
		if ( preg_match('/<amp-position-observer\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-position-observer" src="https://cdn.ampproject.org/v0/amp-position-observer-0.1.js"></script>';
		}

		// The tag 'amp-sidebar' requires including the 'amp-sidebar' extension JavaScript.
		if ( preg_match('/<amp-sidebar\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-sidebar" src="https://cdn.ampproject.org/v0/amp-sidebar-0.1.js"></script>';
		}

		// The tag 'amp-video' requires including the 'amp-video' extension JavaScript.
		if ( preg_match('/<amp-video\b[^>]*>/i', $page) )
		{
			$this->head .= "\n" . '<script async custom-element="amp-video" src="https://cdn.ampproject.org/v0/amp-video-0.1.js"></script>';
		}

		// Custom Style
		if ( !empty($this->style) )
		{
			$this->style = str_replace('\\', '\\\\', $this->style);
			$this->head .= "\n" . '<style amp-custom>' . $this->style . '</style>';
		}

		$page = preg_replace('/<head>/i', '<head>' . "\n" . $this->head, $page, 1);


		$this->head = '';

		// The parent tag of tag 'link rel=stylesheet for fonts' is 'body', but it can only be 'head'.
		$page = preg_replace_callback('/<link\b([^>]*) rel=(("stylesheet")|(\'stylesheet\'))([^>]*)\s*?\/?>/iU', array($this, 'link2_callback'), $page);

		// The mandatory tag 'link rel=canonical' is missing or incorrect.
		$this->head .= '<link rel="canonical" href="' . $this->canonical . '" />';

		// The mandatory tag 'head > style[amp-boilerplate]' is missing or incorrect.
		// The mandatory tag 'noscript > style[amp-boilerplate]' is missing or incorrect.
		// The mandatory tag 'noscript enclosure for boilerplate' is missing or incorrect.
		$this->head .= "\n" . '<style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>';

		$page = preg_replace('/<\/head>/i', $this->head . "\n" . '</head>', $page, 1);


		$page = preg_replace_callback('/<textarea\b([^>]*)>(.*)<\/textarea>/isU', array($this, 'textarea_callback'), $page);

		// Remove blank lines.
		$page = preg_replace('/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/', "\n", $page);

		// Remove end line spaces.
		$page = preg_replace('/[\s\t]+[\r\n]/', "\n", $page);

		$page = preg_replace_callback('/<textarea\b([^>]*)>(.*)<\/textarea>/isU', array($this, 'textarea2_callback'), $page);

		return $page;
	}


	private function css_callback($matches)
	{
		$match = $matches[1];
		$match2 = $matches[3];

		if ( !empty($this->selector_remove_list[$match]) )
		{
			return '';
		}
		elseif ( $this->extened_style )
		{
			return $match . '{' . $match2 . '}';
		}
		elseif ( preg_match('/^@media\b /im', $match) )
		{
			$match2 = preg_replace_callback('/([^{]+)({((?:[^{}]+|(?2))*)})/i', array($this, 'css_callback'), $match2);

			if ( empty($match2) )
			{
				return '';
			}

			return $match . '{' . $match2 . '}';
		}
		elseif ( preg_match('/^@/im', $match) )
		{
			return $match . '{' . $match2 . '}';
		}


		$selector = '';

		$elements = preg_split('/\s*,\s*/', $match);
		foreach ( $elements as $element )
		{
			$included = true;

			$element = preg_replace('/ img\b/i', ' amp-img', $element);
			$element = preg_replace('/ amp-img amp-img$/im', ' amp-img img', $element);

			$element2 = preg_replace('/::?[a-z][a-z-]*/i', '', $element);
			$element2 = preg_replace('/\([^\)]*\)/i', '', $element2);
			$element2 = preg_replace('/\[[^\]]*\]/i', '', $element2);

			preg_match_all('/[\.#]?-?[_a-z]+[_a-z0-9-]*/i', $element2, $keys);
			foreach ( $keys[0] as $key )
			{
				if ( preg_match('/^-((moz)|(ms)|(webkit))/im', $key) )
				{
					continue;
				}
				elseif ( empty($this->selector_list[$key]) )
				{
					$included = false;

					break;
				}
			}

			if ( $included )
			{
				$selector .= ( !empty($selector) ? ',' : '' ) . $element;
			}
		}


		if ( empty($selector) )
		{
			return '';
		}

		return $selector . '{' . $match2 . '}';
	}

	private function media_callback($matches)
	{
		$match = $matches[1];
		$match2 = $matches[3];

		if ( empty($match2) )
		{
			return '';
		}
		elseif ( preg_match('/\(-ms-high-contrast:active\)/i', $match) )
		{
			return '';
		}
		elseif ( preg_match('/\(-ms-high-contrast:none\)/i', $match) )
		{
			return '';
		}
		elseif ( preg_match('/\(-webkit-min-device-pixel-ratio:(\d+(\.\d+)?)\)/i', $match) )
		{
			return '';
		}
		elseif ( preg_match('/\(prefers-reduced-motion:reduce\)/i', $match) )
		{
			return '';
		}
		elseif ( preg_match('/\bprint\b/i', $match) && !preg_match('/\bscreen\b/i', $match) )
		{
			return '';
		}
		elseif ( preg_match('/\bspeech\b/i', $match) && !preg_match('/\bscreen\b/i', $match) )
		{
			return '';
		}
		else
		{
			return '@media ' . $match . '{' . $match2 . '}';
		}
	}
}
