<?php

	/**
	 * class-wordsync-post-processor.php
	 *
	 * Date: 2017/02/09
	 * Time: 01:44 PM
	 */
	class BraveWordsync_Post_Processor extends BraveWordsync_Processor
	{
		protected $slug = 'post';
		protected $version = '1.0';
		protected $details = array('name'=>'Posts', 'desc'=>'Syncs posts, pages, attachments and custom post types', 'dashicon'=>'dashicons-admin-page');

		protected $keyfield = 'post_name';
		protected $matchfields = array('guid');
		protected $valuefields = array('post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_content_filtered', 'post_parent', 'menu_order', 'post_type', 'post_mime_type', 'comment_count', 'terms', 'meta');
		protected $mapfields = array('ID');
		protected $namefield = 'post_name';
		protected $preprocessors = array('user', 'taxonomy');

		private $fieldstoprocess = array('post_content', 'post_title', 'post_excerpt', 'post_content_filtered', 'meta');


		private $orphanedposts = array();

		/**
		 * Loads in user data.
		 * Must SET the $this->localdata array too.
		 */
		public function getLocalData()
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			$taxs = get_taxonomies();

			$excludedpoststatuses = array('auto-draft');
			$excludedposttypes = array('revision', 'attachment');

			$posts = $this->sqlQuery($wpdb->posts, array(), array('post_status'=>$excludedpoststatuses, 'post_type'=>$excludedposttypes));

			$data = array();

			foreach ($posts as $post)
			{
				$thisdata = $post;

				$excludedmeta = array(
					'_edit_lock',
					'_edit_last',
					'_wp_trash_meta_time',
					'_wp_desired_post_slug',
					'_wp_old_slug',
					'_encloseme'
				);

				$thisdata['post_author'] = intval($thisdata['post_author']);

				$meta = $this->sqlQuery($wpdb->postmeta, array('post_id'=>$post['ID']), array('meta_key'=>$excludedmeta));

				//Store this post's meta data
				$thisdata['meta'] = array();
				foreach ($meta as $row)
				{
					$thisdata['meta'][$row['meta_key']] = maybe_unserialize($row['meta_value']);
				}

				//Store this post's taxonomy terms
				$thisdata['terms'] = array();
				foreach ($taxs as $tax)
				{
					$terms = wp_get_post_terms($post['ID'], $tax);
					if (count($terms) > 0)
					{
						$thisdata['terms'][$tax] = array();

						foreach ($terms as $term)
						{
							$thisdata['terms'][$tax][] = $term->slug;
						}
					}

				}

				$dataitem = new BraveWordsync_DataItem($thisdata, $this->makeDataKey($thisdata));

				$data[] = $dataitem;
			}

			$this->localdata = $data;

			return $this->wordsync->makeResult(true);
		}

		public function remotePreprocessor($field, $value, $type)
		{
			if (in_array($field, $this->fieldstoprocess))
			{
				$value = $this->wordsync->getSyncher()->convertRemoteURLToLocal($value);
			}

			if ($field == 'post_author')
			{
				$value = $this->getProcessor('user')->map('ID', $value);
			}

			return $value;
		}

		public function remotePostprocessor($field, $value)
		{

			if ($field == 'post_parent')
			{
				$value = $this->map('ID', $value);
			}

			if ($field == 'meta')
			{
				if (is_array($value) && isset($value['_thumbnail_id']))
				{
					//Warning This could cause problems if the attachment processor was not selected as part of this sync job.
					//The way it works now is that the map function sees that there are no maps available, complains about it and then returns the existing value.
					//Could maybe ignore the thumbnail_id meta field if the attachment processor isnt selected.

					$value['_thumbnail_id'] = $this->getProcessor('attachment')->map("ID", $value['_thumbnail_id']);
				}

			}

			return $value;
		}


		protected function beforeProcessing()
		{
			$this->orphanedposts = array();
		}

		private function checkForOrphanedPost($parentid, $postid)
		{
			/** @var WPDB $wpdb */
			global $wpdb;
			$parent = $wpdb->get_results($wpdb->prepare("SELECT ID FROM ".$wpdb->posts." WHERE ID = %d", $parentid), ARRAY_A);

			if (count($parent) == 0)
			{
				//Post $postid is orphaned!
				//This could happen when it's parent hasnt yet been created, but will be created in future.
				//Unfortunately we wont know what it's ID is until everything has finished processing.
				//So we'll add this post to an orphaned posts list and go back right at the
				//end of the process and use the ID mapping to restore the correct parent. Well thats the theory anyway.

				$this->wordsync->logWarning("WARNING, Post ID " . $postid . " has a parent id of " . $parentid . " which doesnt exist! Marking it as orphaned.");
				$this->orphanedposts[] = $postid;
			}


		}


		protected function performCreateAction($change)
		{
			$di = $change->remotedataitem;

			$data = $di->getFields();

			//$data['post_author'] = $this->getProcessor('user')->map('ID', $data['post_author']);
			//$data['post_parent'] = $this->map('ID', $data['post_parent']);
			//if (isset($data['meta']['_thumbnail_id'])) $data['meta']['_thumbnail_id'] = $this->getProcessor('attachment')->map('ID', $data['meta']['_thumbnail_id']);


			$newpostdata = $data;

			$meta = $data['meta'];
			$terms = $data['terms'];

			unset($data['meta']);
			unset($data['terms']);
			unset($data['ID']);

			$res = wp_insert_post($data, true);
			unset($data);

			if (is_wp_error($res))
			{
				return $this->wordsync->makeResult(false, $res->get_error_message());
			}
			else
			{
				//Success! We now have a new post with id $res.
				$newpostdata['ID'] = $res;

				if ($newpostdata['post_parent'] > 0)
				{
					$this->checkForOrphanedPost($newpostdata['post_parent'], $newpostdata['ID']);
				}


				//Set metadata
				foreach ($meta as $metakey => $metavalue)
				{
					update_post_meta($res, $metakey, $metavalue);
				}

				//Set taxonomy terms
				foreach ($terms as $tax => $termlist)
				{
					$taxonomy_obj = get_taxonomy($tax);
					if ( ! $taxonomy_obj )
					{
						$this->wordsync->logWarning("Warning, invalid taxonomy (" . $tax . ")was specified when trying to create a post.");
						continue;
					}

					if (is_array($termlist))
					{
						//Remove all false entries
						$termlist = array_filter($termlist);
					}

					wp_set_object_terms($res, $termlist, $tax);
				}

				//On success, add this data back into the processor so it can be mapped to the remote data item.
				$this->addNewLocalData($newpostdata, $di);
			}

			return $this->wordsync->makeResult(true, 'Post Created');
		}

		protected function performUpdateAction($change)
		{
			$di = $change->remotedataitem;
			$id = $change->dataitem->getField('ID');

			$data = $di->getFields();

			$data['ID'] = $id;
			//$data['post_author'] = $this->getProcessor('user')->map('ID', $data['post_author']);
			//$data['post_parent'] = $this->map('ID', $data['post_parent']);

			$meta = $data['meta'];
			$terms = $data['terms'];

			unset($data['meta']);
			unset($data['terms']);

			$res = wp_update_post($data, true);

			if (is_wp_error($res))
			{
				return $this->wordsync->makeResult(false, $res->get_error_message());
			}
			else
			{

				if ($data['post_parent'] > 0)
				{
					$this->checkForOrphanedPost($data['post_parent'], $data['ID']);
				}

				//Set metadata
				foreach ($meta as $metakey => $metavalue)
				{
					update_post_meta($res, $metakey, $metavalue);
				}

				$existingmeta = $change->dataitem->getField("meta");
				foreach ($existingmeta as $metakey => $metavalue)
				{
					if (!isset($meta[$metakey])) //Existing meta doesnt exist in the updated data.
					{
						delete_post_meta($id, $metakey);
					}
				}


				//Set taxonomy terms
				foreach ($terms as $tax => $termlist)
				{
					$taxonomy_obj = get_taxonomy($tax);
					if ( ! $taxonomy_obj )
					{
						$this->wordsync->logWarning("Warning, invalid taxonomy (" . $tax . ") was specified when trying to update a post.");
						continue;
					}

					// array = hierarchical, string = non-hierarchical.
					if ( is_array( $termlist ) )
					{
						//Remove all false entries
						$termlist = array_filter($termlist);
					}

					$this->wordsync->log("Setting taxonomy " . $tax . " to term list: ", array('termlist' =>$termlist));

					wp_set_object_terms($res, $termlist, $tax);
				}

			}

			return $this->wordsync->makeResult(true, 'Post Updated');

		}

		protected function performRemoveAction($change)
		{
			$di = $change->dataitem;

			$success = wp_delete_post($di->getField('ID'), true);

			return $this->wordsync->makeResult($success, 'Post Deleted');
		}


		protected function afterProcessing()
		{
			foreach ($this->orphanedposts as $postid)
			{
				//For each orphaned post, map it's parent to a new ID which now hopefully exists.

				$post = get_post($postid);

				$parent = $this->map("ID", $post->post_parent);

				wp_update_post(array("post_parent"=>$parent));
			}
		}

	}