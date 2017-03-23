<?php

	/**
	 * class-wordsync-user-processor.php
	 *
	 * Date: 2016/12/09
	 * Time: 11:05 AM
	 */
	class BraveWordsync_Settings_Processor extends BraveWordsync_Processor
	{
		protected $slug = 'settings';
		protected $version = '1.0';
		protected $details = array('name'=>'Settings', 'desc'=>'Syncs Wordpress settings.', 'dashicon'=>'dashicons-admin-generic');

		protected $keyfield = 'n';
		protected $matchfields = array('n');
		protected $valuefields = array('v', 'al');
		protected $mapfields = array();
		protected $preprocessors = array();
		protected $namefield = 'n';
		protected $fieldnames = array('n'=>'Name', 'v'=>'Value', 'al'=>'Autoload');

		/**
		 * Loads in user data.
		 * Must SET the $this->localdata array too.
		 */
		public function getLocalData()
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			$rawdata = $wpdb->get_results("SELECT option_name, option_value, autoload FROM ".$wpdb->options, ARRAY_A);

			$data = array();

			//Exclude keys which are sensitive or dont make sense to sync.
			$excludedkeys = array('rewrite_rules', 'initial_db_version',
			                      'cron', 'auth_key', 'auth_salt',
			                      'logged_in_key', 'logged_in_salt',
			                      'secure_auth_key', 'secure_auth_salt',
			                      'nonce_key', 'nonce_salt', 'recently_activated',
			                      'auto_core_update_notified', 'db_upgraded', 'bp-emails-unsubscribe-salt',
			                      'wordsync_secret_key', 'siteurl',
			                      'active_plugins', 'db_version', 'uploads_use_yearmonth_folders',
			                      'upload_path', 'upload_url_path', 'auto_updater.lock',
			                      'recently_edited',
			                      'uninstall_plugins',
			                      'wordfence_version'
			                      );

			foreach ($rawdata as $option)
			{
				$key = $option['option_name'];

				if (in_array($key, $excludedkeys)) continue; //Skip all excluded keys.
				if (strpos($key, 'wordsync_') === 0) continue; //Skip all our own options that start with wordsync_.
				if (strpos($key, 'auto_updater') === 0) continue; //Skip all options which start with auto_updater.
				if (strpos($key, '_transient_') === 0) continue; //Skip all options that start with _transient.
				if (strpos($key, '_site_transient_') === 0) continue; //Skip all options that start with _site_transient.


				$thisdata = array(
					'n' => $key,
					'v' => maybe_unserialize($option['option_value']),
					'al' => $option['autoload'],
				);

				$dataitem = new BraveWordsync_DataItem($thisdata, $this->makeDataKey($thisdata));

				$data[] = $dataitem;
			}

			$this->localdata = $data;

			return $this->wordsync->makeResult(true);
		}

		public function remotePreprocessor($field, $value, $type)
		{
			if ($field == 'v')
			{
				$value = $this->wordsync->getSyncher()->convertRemoteURLToLocal($value);
			}
			return $value;
		}

		protected function performCreateAction($change)
		{
			$di = $change->remotedataitem;
			$success = add_option($di->getField('n'), $di->getField('v'), '', $di->getField('al'));

			return $this->wordsync->makeResult($success, 'Setting Created');
		}

		protected function performUpdateAction($change)
		{
			$di = $change->remotedataitem;
			$success = update_option($di->getField('n'), $di->getField('v'), $di->getField('al'));

			return $this->wordsync->makeResult($success, 'Setting Updated');

		}

		protected function performRemoveAction($change)
		{
			$di = $change->dataitem;
			$success = delete_option($di->getField('n'));

			return $this->wordsync->makeResult($success, 'Setting Deleted');
		}

	}