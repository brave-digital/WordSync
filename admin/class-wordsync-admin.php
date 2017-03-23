<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://bravedigital.com/wordsync/
 * @since      0.1.0
 * @author     Brave Digital <wordsync@braveagency.com>
 *
 * @package    BraveWpSync
 * @subpackage BraveWpSync/admin
 */
class BraveWordsync_Admin {

	private $plugin_name;
	private $version;
	/** @var BraveWordsync $wordsync */
	private $wordsync;

	private $adminpage = 'tools.php';
	private $slug = 'wordsync';
	private $hookname = 'tools_page_wordsync';
	private $settingspage = 'wordsync-settings';


	public function __construct($wordsync)
	{
		/** @var BraveWordsync $wordsync */
		$this->wordsync = $wordsync;

		$this->plugin_name = $wordsync->getPluginName();
		$this->version = $wordsync->getVersion();

		$this->register_hooks();
	}

	public function getSlug() { return $this->slug; }
	public function getSettingsPage() { return $this->settingspage; }
	public function getAdminPage() { return $this->adminpage; }
	public function getAdminUrl() { return admin_url($this->adminpage . '?page=' . $this->slug); }
	public function getWordsync() { return $this->wordsync; }

	private function register_hooks()
	{
		$loader = $this->wordsync->getLoader();
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_styles' );
		$loader->add_action('admin_enqueue_scripts', $this, 'enqueue_scripts' );
		$loader->add_action('admin_menu', $this, 'create_menu');
		$loader->add_action('admin_init', $this, 'init_settings');

		$loader->add_action('wp_ajax_wordsync_admin', $this, 'onAdminAjax');
	}


	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.1.0
	 * @param $hook
	 */
	public function enqueue_styles($hook)
	{
		if ($this->hookname != $hook)
		{
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wordsync-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.1.0
	 * @param $hook
	 */
	public function enqueue_scripts($hook)
	{
		if ($this->hookname != $hook)
		{
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wordsync-admin.js', array( 'jquery' ), $this->version, false );
	}


	public function create_menu()
	{
		add_submenu_page($this->adminpage, "WordSync", "WordSync", 'manage_options', $this->slug, array(&$this, 'render_admin_page'));
	}

	private function add_settings_field($optionid, $title, $args = array('type'=>'text'))
	{
		$args = array_merge(array('id'=>$optionid), $args);
		add_settings_field($optionid, $title, array(&$this, 'settings_field_render'), $this->settingspage, $this->slug, $args);
	}

	public function init_settings()
	{
		register_setting($this->slug, BraveWordsync::SETTING_REMOTE_URL);
		register_setting($this->slug, BraveWordsync::SETTING_SECRET_KEY);
		register_setting($this->slug, BraveWordsync::SETTING_PUSH_ENABLED);
		register_setting($this->slug, BraveWordsync::SETTING_SYNC_ENABLED);

		add_settings_section($this->slug, __('WordSync Options', 'wordsync'), '__return_false', $this->settingspage);

		$this->add_settings_field(BraveWordsync::SETTING_REMOTE_URL, __('Remote Url', 'wordsync'), array('type' =>'text', 'classes' =>'code'));
		$this->add_settings_field(BraveWordsync::SETTING_SECRET_KEY, __('Secret Key', 'wordsync'), array('type' =>'text', 'classes' =>'code'));
		$this->add_settings_field(BraveWordsync::SETTING_PUSH_ENABLED, __('Push Enabled', 'wordsync'), array('type' =>'check', 'label' =>__('Allow remote servers access to this Wordpress install\'s data? (Read Permission)', 'wordsync')));
		$this->add_settings_field(BraveWordsync::SETTING_SYNC_ENABLED, __('Sync Enabled', 'wordsync'), array('type' =>'check', 'label' =>__('Allow WP Sync to make changes to this Wordpress install? (Write Permission)', 'wordsync')));

	}

	public function settings_field_render($args)
	{
		$type = (isset($args['type']) ? $args['type'] : 'text');

		$id = $args['id'];
		$value = get_option($id);
		$html = '';
		$classes = (isset($args['classes']) ? $args['classes'] : '');
		$placeholder = (isset($args['placeholder']) ? $args['placeholder'] : '');
		$desc = (isset($args['description']) ? $args['description'] : '');

		switch ($type)
		{
			case 'select':
				$html .= '<select class="'.$classes.'" id="'.$id.'" name="'.$id.'">';
				foreach ($args['choices'] as $key=>$caption)
				{
					$html .= '<option '.(esc_attr($key) == $value ? 'selected="selected"' : '').' value="'.esc_attr($key).'">'.$caption.'</option>';
				}
				$html .= '</select>';

				break;

			case 'check':
				$label = (isset($args['label']) ? $args['label'] : '');
				$checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
				$html .= '<label><input id="'.$id.'" value="1" name="'.$id.'" type="checkbox" '.($checked ? 'checked="checked"' : '').'/> '.$label.'</label>';

				break;
			case 'text': //This was done because when looking back at this function, sometimes you'll look for "case 'text':" and not realise that "default:" means the same thing.
			default:
				$html .= '<input type="text" class="regular-text '.$classes.'" id="'.$id.'" name="'.$id.'" placeholder="'.esc_attr($placeholder).'" value="'.esc_html($value).'"/>';
			break;
		}

		if (!empty($desc))
		{
			$html .= '<p class="description">'.$desc.'</p>';
		}

		echo $html;
	}

	public function render_admin_page()
	{
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		$GLOBALS['wordsync_admin'] = $this;
		if (isset($_REQUEST['settings']) || $this->wordsync->isFirstRun())
		{
			include plugin_dir_path(dirname( __FILE__ )) . 'admin/partials/settingspage.php';
		}
		else
		{
			include plugin_dir_path(dirname( __FILE__ )) . 'admin/partials/mainpage.php';
		}
	}

	protected function makeResult($success, $msg = '', $args = array())
	{
		return array_merge( array('success'=>$success, 'msg'=>$msg), $args);
	}

	public function onAdminAjax()
	{
		if (!headers_sent()) header('Content-Type: application/json');

		$cmd = isset($_REQUEST['command']) ? $_REQUEST['command'] : '';
		$data = isset($_REQUEST['data']) ? $_REQUEST['data'] : array();
		$response = $this->makeResult(false, 'Invalid command');

		switch ($cmd)
		{
			case 'startsync':

				if (isset($data['remoteurl']) && isset($data['processors']))
				{
					$syncer = $this->getWordsync()->getSyncher();

					$response = $syncer->startSyncJob($data['remoteurl'], $data['processors']);
				}
				else
				{
					$response = $this->makeResult(false, __('Invalid Remote URL or no data was selected to be synced.', 'wordsync'));
				}

				break;

			case 'continuesync':

				if (isset($data['jobid']))
				{
					$syncer = $this->getWordsync()->getSyncher();
					$params = array();

					$response = $syncer->continueSyncJob(esc_attr($data['jobid']), $data);
				}

				break;

			case 'getchanges':

				if (isset($data['jobid']))
				{
					$syncer = $this->getWordsync()->getSyncher();
					$response = $syncer->getJobChangeReviewList(esc_attr($data['jobid']));
				}

				break;



			case 'getlog':

				$response = $this->makeResult(true, '', array('log'=>$this->getWordsync()->getLog()));

				break;

			case 'canceljob':

				if (isset($data['jobid']))
				{
					$syncer = $this->getWordsync()->getSyncher();

					$deleted = $syncer->cancelSyncJob(esc_attr($data['jobid']));
					$response = $this->makeResult($deleted, ($deleted ? __('Job deleted successfully.', 'wordsync') : __('Job deleted unsuccessfully.', 'wordsync')));
				}

				break;

		}


		echo json_encode($response);
		wp_die();
	}
}
