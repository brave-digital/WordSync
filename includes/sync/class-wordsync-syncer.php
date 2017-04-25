<?php

	/**
	 * class-bravesyncer.php
	 *
	 * Date: 2016/12/01
	 * Time: 04:33 PM
	 */
	class BraveWordsync_Syncer
	{
		/** @var BraveWordsync $wordsync */
		private $wordsync;
		private $remoteurl;

		private $localinfo;
		private $remoteinfo;

		/**
		 * local and remote infos are arrays with the following structure:
		 *  'wordsync_version' => '1.0.0',
			'wp_version' => '4.7.2',
			'db_version' => '38590',
			'siteurl' => 'http://192.168.2.200/braveplugins',
			'homeurl' => 'http://192.168.2.200/braveplugins',
			'processors' => array(
				'user' => array(
					'slug' => 'user',
					'version' => '1.0',
					'preprocessors' => array(),
					'name' => 'Users',
					'desc' => 'Syncs users. Newly created users will have to reset their passwords.',
					'dashicon' => 'dashicons-admin-users',
				),
				'settings' => array(
					'slug' => 'settings',
					'version' => '1.0',
					'preprocessors' => array(),
					'name' => 'Settings',
					'desc' => 'Syncs Wordpress settings.',
					'dashicon' => 'dashicons-admin-generic',
				),
				'taxonomy' => array(
					'slug' => 'taxonomy',
					'version' => '1.0',
					'preprocessors' => array(),
					'name' => 'Taxonomies',
					'desc' => 'Syncs categories and tags.',
					'dashicon' => 'dashicons-tag',
				),
		 */

		/** @var array BraveWpsync_Processor $processors */
		private $processors = array();

		private $currentjob = null;
		private $cache = array(); //Cache of variables which are used often in this execution of the php code. Only used for this execution run.

		/**
		 * BraveSyncer constructor.
		 * @param $wordsync - The main plugin instance
		 */
		public function __construct($wordsync)
		{
			$this->wordsync = $wordsync;
			$this->register_hooks();


			//TODO: Dynamically load in all .php files in the processors directory and call loadProcessor on each one.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'sync/processors/class-wordsync-user-processor.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'sync/processors/class-wordsync-settings-processor.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'sync/processors/class-wordsync-taxonomy-processor.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'sync/processors/class-wordsync-post-processor.php';
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'sync/processors/class-wordsync-attachment-processor.php';

			$this->loadProcessor("BraveWordsync_User_Processor");
			$this->loadProcessor("BraveWordsync_Settings_Processor");
			$this->loadProcessor("BraveWordsync_Taxonomy_Processor");
			$this->loadProcessor("BraveWordsync_Post_Processor");
			$this->loadProcessor("BraveWordsync_Attachment_Processor");

		}

		protected function register_hooks()
		{
			$loader = $this->wordsync->getLoader();
			$loader->add_action('wp_ajax_nopriv_bravewordsync', $this, 'onRemote');
		}

		protected function loadProcessor($class)
		{
			/** @var BraveWordsync_Processor $processor */
			$processor = new $class($this->wordsync, $this);
			$slug = $processor->getSlug();

			$this->processors[$slug] = $processor;

			return $processor;
		}


		public function getInfo()
		{
			global $wpdb;

			$processors = array();
			foreach ($this->processors as $key=>$proc)
			{
				/** @var BraveWordsync_Processor $proc */
				$processors[$key] = array_merge(array('slug'=>$key), $proc->getDetails());
			}

			$uploads = wp_upload_dir();

			$res = array(
				'wordsync_version'=>$this->wordsync->getVersion(),
				'wp_version'=> get_bloginfo('version'),
				'db_version' => get_option('db_version'),
				'siteurl' => get_option('siteurl'),
				'homeurl' => get_option('home'),
				'uploadsurl' => $uploads['baseurl'],
				'dbprefix' => $wpdb->prefix,
				'processors' => $processors
			);

			return $res;
		}


		public function remoteUploadsUrl()
		{
			if (isset($this->remoteinfo['uploadsurl'])) return $this->remoteinfo['uploadsurl'];

			return false;
		}


		public function debugOutput()
		{
			echo '<h2>Debug Output</h2><pre>';

			echo 'GetInfo: ';
			print_r($this->getInfo());

			echo '<br/>Available Processors: <br/>';
			foreach ($this->processors as $slug=>$processor)
			{
				echo ' "'.$slug.'" - '.get_class($processor)."\n";
			}

			echo '</pre>';
		}


		protected function makeResult($success, $msg = '', $args = array())
		{
			//Convenience function which calls the main wordsync->makeResult function.
			return $this->wordsync->makeResult($success, $msg, $args);
		}

		public function sendRemoteCommand($cmd, $args = array())
		{
			$curl = curl_init();

			$this->wordsync->log("Sending Remote Command: " . $cmd);

			$res = $this->makeResult(false, 'Unable to send remote command.', array('cmd'=>$cmd, 'args'=>$args));

			try
			{
				curl_setopt_array($curl, array(
					CURLOPT_RETURNTRANSFER => 1,
					CURLOPT_URL => $this->remoteurl,
					CURLOPT_USERAGENT => 'BraveWPSyncPlugin',
					CURLOPT_POST => 1,
					CURLOPT_POSTFIELDS => array_merge(
						array('action'=>'bravewordsync', 'command'=>$cmd, 'secret'=>$this->wordsync->getHashedKey()),
						$args
					)
				));

				$rawresponse = curl_exec($curl);

				$response = json_decode($rawresponse, true);

				if (is_array($response) && isset($response['success']))
				{
					$res = $response;
				}
				else
				{
					$res = $this->makeResult(false, 'The remote server did not respond correctly. (Check that the plugin is installed and enabled on the remote install.)', array('url'=>$this->remoteurl, 'data'=>$rawresponse));
				}

			}
			catch(Exception $e)
			{
				$this->wordsync->logError('Error while sending remote command: ' . $e->getMessage(), array('error' =>$e, 'cmd' =>$cmd, 'args' =>$args));
				$res = $this->makeResult(false, 'Error while sending remote command: '.$e->getMessage(), array('error'=>$e, 'cmd'=>$cmd, 'args'=>$args));
			}

			curl_close($curl);

			return $res;
		}

		public function onRemote()
		{
			ob_clean();
			if (!headers_sent()) header('Content-Type: application/json');

			$result = array();
			try
			{

				//TODO: Get User Agent string and make sure that it's the same one we sent to the SendRemoteCommand function.

			$command = (isset($_REQUEST['command']) ? $_REQUEST['command'] : '');
			$secret = (isset($_REQUEST['secret']) ? $_REQUEST['secret'] : '');

			if (!$this->wordsync->validateSecretKey($secret))
			{
				$result = $this->makeResult(false, 'Invalid Secret Key.');
			}
			else
			{

				switch ($command)
				{
					//Remote requests info about the wordpress install and wpsync plugin and available processors.
					case 'info':
						$this->wordsync->log("Got Remote Request for Info");

						$info   = $this->getInfo();
						$result = $this->makeResult(true, '', array('info' => $info));
						break;

					//Remote requests that one of the processors pull local data and return it in json format.
					case 'pull':


						if (!$this->wordsync->getSetting(BraveWordsync::SETTING_PUSH_ENABLED))
						{
							$result = $this->makeResult(false, 'Push is not enabled on this server.');
						}
						else
						{
							$processor = trim(isset($_REQUEST['processor']) ? $_REQUEST['processor'] : '');
							if (isset($this->processors[$processor]))
							{
								$this->wordsync->log("Got Remote Request to Pull from processor " . $processor);

								$data = $this->processors[$processor]->localDataToJSON();
								$result = $this->makeResult(true, '', array('data'=>$data));
							}
							else
							{
								$result = $this->makeResult(false, 'Invalid processor.');
							}
						}

						break;
					default:
						$result = $this->makeResult(false, 'Invalid command.');
				}
			}

			}
			catch(Exception $e)
			{
				$this->wordsync->logError("Error while running command " . $command . "! ", array('msg' =>$e->getMessage(), 'file' =>$e->getFile(), 'line' =>$e->getLine(), 'trace' =>$e->getTraceAsString()));
				$result = $this->makeResult(false, $e->getMessage() . ' on line '.$e->getLine(). ' in '.$e->getFile());
			}

			echo json_encode($result);
			wp_die();
		}


		public function performProcessor($processor)
		{
			if (isset($this->processors[$processor]))
			{
				if (!isset($this->remoteinfo['processors'][$processor]))
				{
					return $this->makeResult(false, 'The remote install does not have the ' . $processor . ' processor installed.');
				}

				/** @var BraveWordsync_Processor $pr */
				$pr = $this->processors[$processor];

				$this->wordsync->log('Performing processor: ' . $pr->getSlug());

				if (!$pr->isLocalDataLoaded())
				{
					$localres = $pr->getLocalData();
					if (!$this->wordsync->checkResult($localres))
					{
						$this->wordsync->logError('Local data load failed.', $localres);
						return $localres;
					}
				}


				//Get remote data via curl.
				if  (!$pr->isRemoteDataLoaded())
				{
					$remoteres = $pr->getRemoteData();
					if (!$this->wordsync->checkResult($remoteres))
					{
						$this->wordsync->logError('Remote data load failed.', $remoteres);
						return $remoteres;
					}
				}


				$pr->compareLocalAndRemote();



				/*
				$this->wpsync->log(count($pr->changes) . ' changes created.');
				foreach ($pr->changes as $change)
				{
					// @var BraveWordsync_Change $change
					$this->wpsync->log($change->toString());
				}
				*/

				return $this->wordsync->makeResult(true, $pr->getSlug() . ' matched.');
			}
			else
			{
				return $this->wordsync->makeResult(false, 'Invalid processor: ' . $processor);
			}
		}

		protected function setRemoteUrl($url)
		{
			$adminajax = "admin-ajax.php";
			$wpadmin = 'wp-admin/';
			$origurl = $url;

			//Try to add wp-admin/admin-ajax.php if it's not supplied.

			if (substr($url, -strlen($adminajax)) !== $adminajax) //Url doesnt end in admin-ajax.php
			{
				//At this point the url could either be the root, or the wp-admin directory.

				$url = trailingslashit($url);

				if (substr($url, -strlen($wpadmin)) === $wpadmin) //Url ends in wp-admin/
				{
					//Just need to add admin-ajax.
					$url .= "admin-ajax.php";
				}
				else
				{
					//Unable to find anything familiar. Assume that this is the base wp directory.
					$url .= 'wp-admin/admin-ajax.php';
				}
			}

			if (wp_http_validate_url($url))
			{
				$this->remoteurl = $url;
				return true;
			}

			$this->wordsync->logError("Unable to validate remote url! Given '" . $origurl . "' and processed to '" . $url . "' which still isnt valid.");
			return false;
		}

		/**
		 * Scans for any occurances of the remote url and converts them into the local url in the given string.
		 * Accepts a string or an array. Arrays are scanned deeply and all instances of strings are converted.
		 *
		 * @param string|array $string
		 * @return string|array
		 */
		public function convertRemoteURLToLocal($string)
		{
			if (!isset($this->cache['localurl']))
			{
				$local  = $this->localinfo['siteurl'];
				$remote = $this->remoteinfo['siteurl'];

				$this->cache['localurl'] = $local;
				$this->cache['remoteurl'] = $remote;

				//Handle html entity encoded version of the url.
				$local  = urlencode($local);
				$remote = urlencode($remote);

				$this->cache['localurlhtml'] = $local;
				$this->cache['remoteurlhtml'] = $remote;


				//$this->wpsync->log("Looking for RemoteURL and Swapping with Local URL", $this->cache);
			}

			if (is_array($string))
			{
				foreach ($string as $key=>$value)
				{
					if (is_array($value) || is_string($value))
					{
						$string[$key] = $this->convertRemoteURLToLocal($value);
					}
				}
			}
			else if (is_string($string))
			{
				$string = str_replace($this->cache['remoteurl'], $this->cache['localurl'], $string);
				$string = str_replace($this->cache['remoteurlhtml'], $this->cache['localurlhtml'], $string);
			}

			return $string;
		}

		/**
		 * Helper function for sorting the processors by their prerequisites.
		 * @param $procs
		 * @param $slug
		 */
		private function createOrderRecord(&$procs, $slug)
		{
			$details = $this->processors[$slug]->getDetails();
			$procs[$slug] = array('deps'=> $details['preprocessors'], 'slug'=>$slug, 'mark'=>0);

			foreach ($details['preprocessors'] as $dp)
			{
				$this->createOrderRecord($procs, $dp);
			}
		}

		/**
		 * Helper function for sorting the processors by their prerequisites.
		 * @param $node
		 * @param $unorderedlist
		 * @param $orderedlist
		 * @return bool
		 */
		private function sortOrderRecord(&$node, &$unorderedlist, &$orderedlist)
		{

			if ($node['mark'] == 1)
			{
				return false;
			}
			if ($node['mark'] == 0)
			{
				$node['mark'] = 1;
				foreach ($node['deps'] as &$dep)
				{
					if (isset($unorderedlist[$dep]))
					{
						$ret = $this->sortOrderRecord($unorderedlist[$dep], $unorderedlist, $orderedlist);

						if ($ret === false)
						{
							return false;
						}
					}
				}
				unset($dep);

				$node['mark'] = 2;
				//array_unshift($orderedlist, $node);
				$orderedlist[$node['slug']] = $node;
			}

			return true;
		}


		/**
		 * Sorts the processors by their prerequisites.
		 * Accepts an array of slugs, returns an array of slugs which have been sorted based on each processors's prerequisites.
		 * @param $processors - array of slugs
		 * @return array of slugs
		 */
		protected function orderProcessors($processors)
		{
			//Setup temporary array of processors and their dependencies:
			$procs = array();


			foreach ($processors as $p)
			{
				$this->createOrderRecord($procs, $p);
			}


			$orderedprocessors = array();



			$stillleft = true;

			while ($stillleft)
			{
				$stillleft = false;
				foreach ($procs as &$p)
				{
					if ($p['mark'] == 0)
					{
						$stillleft = true;
						if (!$this->sortOrderRecord($p, $procs, $orderedprocessors)) $stillleft = false;
					}
				}
				unset($p);
			}

			$result = array();

			foreach ($orderedprocessors as $proc)
			{
				$result[] = $proc['slug'];
			}
			unset($orderedprocessors);
			unset($procs);

			return $result;
		}

		public function getProcessor($slug)
		{
			return $this->processors[$slug];
		}

		/**
		 * Preps the system to make a connection to the remote install.
		 * Checks that the remote server is reachable and that it's versions matches the local install.
		 *
		 * @param $remoteurl
		 * @return array|mixed|object
		 */
		public function checkAndLoadRemote($remoteurl)
		{
			if (!$this->setRemoteUrl($remoteurl))
			{
				return $this->makeResult(false, 'Invalid Remote URL');
			}

			if (!$this->wordsync->getSetting(BraveWordsync::SETTING_SYNC_ENABLED))
			{
				return $this->makeResult(false, 'Syncing is not enabled on this install.');
			}

			//Retrieve remote info + check connectivity.
			$this->localinfo = $this->getInfo();
			$result = $this->sendRemoteCommand("info");
			if ($this->wordsync->checkResult($result))
			{
				$this->remoteinfo = $result['info'];


				if ($this->remoteinfo['wordsync_version'] < $this->localinfo['wordsync_version'])
				{
					return $this->makeResult(false, 'The remote install has an older version of the WordSync plugin ('.$this->remoteinfo['wordsync_version'].') and you\'re running '.$this->localinfo['wordsync_version']);
				}

				if ($this->remoteinfo['wp_version'] != $this->localinfo['wp_version'])
				{
					return $this->makeResult(false, 'The remote install has a different WordPress version. ('.$this->remoteinfo['wp_version'].') and you\'re running '.$this->localinfo['wp_version']);
				}

				if ($this->remoteinfo['db_version'] != $this->localinfo['db_version'])
				{
					return $this->makeResult(false, 'The remote install has a different WordPress Database version. ('.$this->remoteinfo['db_version'].') and you\'re running '.$this->localinfo['db_version']);
				}

				return $this->makeResult(true, 'Remote loaded and ready.');
			}
			else
			{
				$this->wordsync->logError("Remote server returned an error!", $result);
				return $result;
			}
		}

		/**
		 * Starts a sync job.
		 * @param $remoteurl - the url of the site to sync to, must be just a regular url of the homepage of the site.
		 * @param $processors - array of processor slugs to use.
		 * @return array
		 */
		public function startSyncJob($remoteurl, $processors)
		{
			$result = $this->checkAndLoadRemote($remoteurl);
			if ($this->wordsync->checkResult($result))
			{
				$orderedprocessors = $this->orderProcessors($processors);

				$this->wordsync->log("Starting Job! Processors to be run are: ", $orderedprocessors);

				$currentjob = new BraveWordsync_Job($this->wordsync, $remoteurl, $orderedprocessors);

				return $currentjob->doJobStep(); //Do the first sync job step which is to setup and save the job
			}
			else
			{
				return $result;
			}
		}

		/**
		 * Continues an already started job, passing any user defined parameters to the job in the Params argument.
		 *
		 * @param $jobid
		 * @param array $params
		 * @return array|mixed|object
		 */
		public function continueSyncJob($jobid, $params = array())
		{

			$this->wordsync->log("Continuing job " . $jobid);

			if (!BraveWordsync_Job::jobExists($jobid))
			{
				$this->wordsync->logError("Job " . $jobid . " does not exist or has expired!");

				return $this->makeResult(false, 'Invalid Job or the Job has expired.');

			}

			$this->wordsync->log("Job exists. Loading from transient...");

			$job = new BraveWordsync_Job($this->wordsync, '', array(), $jobid);

			if ($job)
			{
				$result = $this->checkAndLoadRemote($job->getRemoteUrl());

				if ($this->wordsync->checkResult($result))
				{
					return $job->doJobStep($params);
				}
				else
				{
					return $result;
				}
			}
			else
			{
				return $this->makeResult(false, 'Unable to load this job!');
			}
		}

		/**
		 * @param $jobid
		 * @return array|mixed|object
		 */
		public function getJobChangeReviewList($jobid)
		{
			if (!BraveWordsync_Job::jobExists($jobid))
			{
				$this->wordsync->logError("Job " . $jobid . " does not exist or has expired!");

				return $this->makeResult(false, 'Invalid Job or the Job has expired.');
			}

			$job = new BraveWordsync_Job($this->wordsync, '', array(), $jobid);

			if ($job)
			{
				$result = $this->checkAndLoadRemote($job->getRemoteUrl());

				if ($this->wordsync->checkResult($result))
				{
					return $this->makeResult(true, '', array('changes'=>$job->getChangesReviewJSON()));
				}
				else
				{
					return $result;
				}
			}
			else
			{
				return $this->makeResult(false, 'Unable to load this job!');
			}
		}

		public function cancelSyncJob($jobid)
		{
			if (BraveWordsync_Job::jobExists($jobid))
			{
				$job = new BraveWordsync_Job($this->wordsync, '', array(), $jobid);
				$job->deleteJob();

				return true;
			}

			return false;
		}

	}