<?php
if ( !defined('ABSPATH') )
{
	exit;
}

class PWAMPConversion
{
	public function __construct()
	{
	}

	public function __destruct()
	{
	}


	public function convert($page, $home_url, $data, $theme, $plugins, $style, $templates)
	{
		require_once plugin_dir_path(__FILE__) . 'transcoding.php';

		$transcoding = new PWAMPTranscoding();

		$transcoding->init($home_url, $data);


		if ( is_plugin_active('pwamp-extension/pwamp.php') )
		{
			require_once plugin_dir_path(__FILE__) . '../../pwamp-extension/pwamp/extension.php';

			$extension = new PWAMPExtension();

			$extension->init($transcoding);
		}


		if ( !empty($extension) )
		{
			$page = $extension->pretranscode($page);
		}

		$page = $transcoding->transcode_html($page);

		if ( !empty($extension) )
		{
			$page = $extension->transcode($page);
		}

		$page = $transcoding->transcode_head($page);

		if ( !empty($extension) )
		{
			$page = $extension->posttranscode($page);
		}

		return $page;
	}
}
