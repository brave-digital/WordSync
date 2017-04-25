<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://bravedigital.com
 * @since      0.1.0
 *
 * @package    BraveWpsync
 * @subpackage BraveWpsync/includes
 */

class BraveWordsync {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      BraveWordsync_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	protected $plugin_name;
	protected $version;
	/** @var  BraveWordsync_Admin $plugin_admin */
	protected $plugin_admin;
	/** @var BraveWordsync_Syncer $syncher */
	protected $syncher;
	/** @var KLogger\Logger $logger */
	protected $logger;

	//Settings:
	const SETTING_REMOTE_URL = 'wordsync_remote_url';
	const SETTING_SECRET_KEY = 'wordsync_secret_key';
	const SETTING_PUSH_ENABLED = 'wordsync_push_enabled'; //Allow this wordpress install to give out information over admin-ajax.php
	const SETTING_SYNC_ENABLED = 'wordsync_sync_enabled'; //Allow this wordpress install to sync itself to external data.

	private $firstrun;

	public function __construct() {

		$this->plugin_name = 'wordsync';
		$this->version = '0.1.1';

		$this->loadDependencies();

		//Initalise Log file:
		$uploaddir = wp_upload_dir();
		$logdir = $uploaddir['basedir'].DIRECTORY_SEPARATOR.'wordsync-logs';
		if (!file_exists($logdir))
		{
			wp_mkdir_p($logdir);
		}
		$this->logger = new KLogger\Logger($logdir);

		$this->setLocale();

		$this->plugin_admin = new BraveWordsync_Admin($this);
		$this->syncher = new BraveWordsync_Syncer($this);

		$this->firstrun = strlen(get_option($this::SETTING_SECRET_KEY)."") == 0;
	}


	public function log($message, array $context = array())
	{
		return $this->logger->log(Psr\Log\LogLevel::INFO, $message, $context);
	}

	public function logWarning($message, array $context = array())
	{
		return $this->logger->log(Psr\Log\LogLevel::WARNING, $message, $context);
	}

	public function logError($message, array $context = array())
	{
		return $this->logger->log(Psr\Log\LogLevel::ERROR, $message, $context);
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wpsync_Loader. Orchestrates the hooks of the plugin.
	 * - Wpsync_i18n. Defines internationalization functionality.
	 * - Wpsync_Admin. Defines all hooks for the admin area.
	 * - Wpsync_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    0.1.0
	 * @access   private
	 */
	private function loadDependencies()
	{
		$basepath = plugin_dir_path(dirname( __FILE__ ));
		//Plugin framework
		require_once $basepath . 'includes/class-wordsync-loader.php';
		require_once $basepath . 'admin/class-wordsync-admin.php';

		//Logging
		if (!interface_exists("\\Psr\\Log\\LoggerInterface"))
		{
			require_once $basepath . 'includes/logging/LoggerInterface.php';
		}

		if (!class_exists("\\Psr\\Log\\AbstractLogger"))
		{
			require_once $basepath . 'includes/logging/AbstractLogger.php';
		}

		if (!class_exists("\\Psr\\Log\\LogLevel"))
		{
			require_once $basepath . 'includes/logging/LogLevel.php';
		}

		if (!class_exists("\\KLogger\\Logger"))
		{
			require_once $basepath . 'includes/logging/logger.php';
		}


		//Syncer
		require_once $basepath . 'includes/sync/class-wordsync-dataitem.php';
		require_once $basepath . 'includes/sync/class-wordsync-change.php';
		require_once $basepath . 'includes/sync/class-wordsync-processor.php';
		require_once $basepath . 'includes/sync/class-wordsync-syncer.php';
		require_once $basepath . 'includes/sync/class-wordsync-job.php';

		//3rd party libraries
		require_once $basepath . 'includes/vendor/finediff.php';

		$this->loader = new BraveWordsync_Loader();
	}


	private function setLocale()
	{
		$this->loader->add_action( 'plugins_loaded', $this, 'loadTextDomain' );
	}

	public function loadTextDomain()
	{
		load_plugin_textdomain(
			'wordsync',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	public function getCurrentLog()
	{
		return implode("\n", $this->logger->getLastLogLines());
	}

	public function getLog()
	{
		try
		{
			$log = file_get_contents($this->logger->getLogFilePath());
		}
		catch(Exception $e)
		{
			$log = __('Error reading log file. '.$e->getMessage(), 'wordsync');
		}
		return $log;
	}


	/**
	 * Most functions need a way to return a success or failure plus a simple message or additional data. This function builds a little object which contains
	 * a success boolean, an optional message and any additional data you wish to store. This provides a universal way to return data from functions.
	 *
	 * @param $success
	 * @param string $msg
	 * @param array $args
	 * @return array
	 */
	public function makeResult($success, $msg = '', $args = array())
	{

		if (is_wp_error($msg)) $msg = $msg->get_error_message();

		return array_merge( array('success'=>$success, 'msg'=>$msg), $args);
	}

	/**
	 * Provides a simple way to check that an object is indeed a result, and if that result is successful.
	 *
	 * @param $res
	 * @return bool
	 */
	public function checkResult($res)
	{
		return (is_array($res) && isset($res['success']) && $res['success']);
	}

	public function getSetting($setting)
	{
		return get_option($setting);
	}

	public function isFirstRun()
	{
		return $this->firstrun;
	}

	/**
	 * Salts and hashes the secret keys used to test if the installs are authorised. Extra paranoid users can change the salt used below.
	 * @return string
	 */
	public function getHashedKey()
	{
		$key = get_option($this::SETTING_SECRET_KEY);
		return md5('BRAVE_'.$key.'_wordsync');
	}

	public function getSyncher()
	{
		return $this->syncher;
	}

	public function validateSecretKey($remotesecret)
	{
		if (trim($remotesecret) == $this->getHashedKey())
		{
			return true;
		}

		return false;
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.1.0
	 */
	public function run() {
		$this->loader->run();
	}

	public function getPluginName() {
		return $this->plugin_name;
	}

	public function getLoader() {
		return $this->loader;
	}

	public function getVersion() {
		return $this->version;
	}

}
