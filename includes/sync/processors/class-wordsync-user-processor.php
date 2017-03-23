<?php

	/**
	 * class-wordsync-user-processor.php
	 *
	 * Date: 2016/12/09
	 * Time: 11:05 AM
	 */
	class BraveWordsync_User_Processor extends BraveWordsync_Processor
	{
		protected $slug = 'user';
		protected $version = '1.0';
		protected $details = array('name'=>'Users', 'desc'=>'Syncs users. Newly created users will have to reset their passwords.', 'dashicon'=>'dashicons-admin-users');

		protected $keyfield = 'user_login';
		protected $matchfields = array('user_email', 'user_login');
		protected $valuefields = array('user_login', 'user_email', 'display_name', 'user_nicename', 'user_url', 'user_status', 'meta', 'roles');
		protected $mapfields = array('ID');
		protected $namefield = 'display_name';
		protected $preprocessors = array();

		/**
		 * Loads in user data.
		 * Must SET the $this->localdata array too.
		 */
		public function getLocalData()
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			$args = '';
			$rawdata = get_users($args);

			$data = array();

			foreach ($rawdata as $user)
			{
				/** @var WP_User $user */
				$thisdata = array(
					'ID'=> $user->ID,
					'user_login' => $user->user_login,
					'user_email' => $user->user_email,
					'display_name' => $user->display_name,
					'user_nicename' => $user->user_nicename,
					'user_url'=> $user->user_url,
					'user_status' => $user->user_status,
					'roles'=>$user->roles,
				    'meta'=>array(),
				);

				$excludedmeta = array(
					'last_activity',
					'session_tokens',
					'locale',
					'wp_user-settings-time',
					'wp_dashboard_quick_press_last_post_id',
					'_woocommerce_persistent_cart',
					'last_update',
					'dismissed_wp_pointers',
					);

				$meta = $this->sqlQuery($wpdb->usermeta, array('user_id'=>$user->ID), array('meta_key'=>$excludedmeta));
				//$meta = $wpdb->get_results("SELECT * FROM ".$wpdb->usermeta." WHERE user_id = ".esc_sql($user->ID)." AND meta_key NOT IN (".implode(',', $excludedmeta).")", ARRAY_A);

				foreach ($meta as $row)
				{
					//if (in_array($row['meta_key'], $excludedmeta)) continue;
					$thisdata['meta'][$row['meta_key']] = maybe_unserialize($row['meta_value']);
				}

				$dataitem = new BraveWordsync_DataItem($thisdata, $this->makeDataKey($thisdata));

				$data[] = $dataitem;
			}

			$this->localdata = $data;

			return $this->wordsync->makeResult(true);
		}


		protected function performCreateAction($change)
		{
			$di = $change->remotedataitem;

			$this->wordsync->log("User create action got the following change: ", $change->toJSON());

			$thisdata = $di->getFields(array('meta', 'roles'));
			$thismeta = $di->getField('meta');

			//TODO: User Roles migration!
			//$thisroles = $di->getField('roles');

			$res = wp_insert_user($thisdata);
			$success = !is_wp_error($res);

			if ($success)
			{

				//$res now holds the new user's id.
				$thisdata['ID'] = $res;
				$thisdata['meta'] = $thismeta;
				//TODO: User Roles fill in.
				$thisdata['roles'] = array();

				//On success add this user back into the processor and link it with this remote data item so it's available to be a mapping.
				$this->addNewLocalData($thisdata, $di);

				foreach ($thismeta as $metakey=>$metavalue)
				{
					update_user_meta($res, $metakey, $metavalue);
				}
			}

			return $this->wordsync->makeResult($success, 'User Created');
		}

		protected function performUpdateAction($change)
		{
			$di = $change->remotedataitem;
			$id = $change->dataitem->getField('ID');

			$userdata = array('ID'=>$id);

			foreach ($change->fields as $field)
			{
				if ($field == 'roles')
				{
					$this->wordsync->logError("WARNING! I dont know how to update user roles yet!", array('roles' =>$di->getField('roles')));
				}
				else if ($field == 'meta')
				{

					//Do an entire CUD (create, update, delete) cycle for the meta subfield.

					//Create / update all the remote data meta
					$remotemeta = $di->getField('meta');
					foreach ($remotemeta as $metakey=>$metavalue)
					{
						if (is_null($metavalue))
						{
							delete_user_meta($id, $metakey);
						}
						else
						{
							update_user_meta($id, $metakey, $metavalue);
						}
					}

					//Delete any local data meta that doesnt exist on the remote data.
					$localmeta = $change->dataitem->getField('meta');
					foreach ($localmeta as $metakey=>$metavalue)
					{
						if (!isset($remotemeta[$metakey]))
						{
							delete_user_meta($id, $metakey);
						}
					}

				}
				else
				{
					$userdata[$field] = $di->getField($field);
				}
			}

			$res = wp_update_user($userdata);

			return $this->wordsync->makeResult(!is_wp_error($res), 'User Updated', array('error' =>$res));

		}

		protected function performRemoveAction($change)
		{
			$di = $change->dataitem;

			$success = wp_delete_user($di->getField('ID'));

			return $this->wordsync->makeResult($success, 'User Deleted');
		}


	}