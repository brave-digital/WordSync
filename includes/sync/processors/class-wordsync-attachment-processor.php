<?php

	/**
	 * class-wordsync-attachment-processor.php
	 *
	 * Date: 2017/02/24
	 * Time: 11:41 AM
	 */
	class BraveWordsync_Attachment_Processor extends BraveWordsync_Processor
	{
		protected $slug = 'attachment';
		protected $version = '1.0';
		protected $details = array('name'=>'Attachments', 'desc'=>'Syncs images and uploaded files.', 'dashicon'=>'dashicons-images-alt2');

		protected $keyfield = 'path';
		protected $matchfields = array('path', 'image_data');
		protected $valuefields = array('filename', 'path', 'image_data', 'image_meta', 'width', 'height', 'dir','post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_content_filtered', 'post_parent', 'menu_order', 'post_type', 'post_mime_type', 'comment_count');
		protected $mapfields = array('ID');
		protected $namefield = 'thumb';
		protected $preprocessors = array('user', 'taxonomy', 'post');

		private $fieldstoprocess = array('filename');

		/**
		 * Loads in user data.
		 * Must SET the $this->localdata array too.
		 */
		public function getLocalData()
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			$excludedpoststatuses = array('auto-draft');
			$excludedposttypes = array();

			$posts = $this->sqlQuery($wpdb->posts, array('post_type'=>'attachment'), array('post_status'=>$excludedpoststatuses, 'post_type'=>$excludedposttypes));

			$data = array();

			$uploadinfo = wp_upload_dir();
			$uploaddir = $uploadinfo['basedir'];
			$uploadurl = $uploadinfo['baseurl'];

			foreach ($posts as $post)
			{


				//$meta = $this->sqlQuery($wpdb->postmeta, array('post_id'=>$post['ID']), array());



				$imgmeta = get_post_meta($post['ID'], '_wp_attachment_metadata', true);
				$filename = get_post_meta($post['ID'], '_wp_attached_file', true);


				$thisdata = $post;

				$extras = array(
					'width' => $imgmeta['width'],
					'height' => $imgmeta['height'],
					'path' => $filename,
					'image_meta' => $imgmeta['image_meta'],
					'filename' => basename($filename),
					'dir' => dirname($filename),
				  'image_data' => hash_file('md5', $uploaddir.DIRECTORY_SEPARATOR.$filename),
				);

				$thisdata = array_merge($thisdata, $extras);

				if ($thisdata['image_data'] === false)
				{
					$thisdata['image_data'] = 'Image Not Found';
					$thisdata['thumb'] = null;
				}
				else
				{
					$thumbimg = (isset($imgmeta['sizes']['thumbnail'])) ? $thisdata['dir'].'/'.$imgmeta['sizes']['thumbnail']['file'] : $thisdata['path'];
					$thisdata['thumb'] = '<img class="attachment_thumbnail" src="'.$uploadurl.'/'.($thumbimg).'"/>';
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

			if ($field == 'post_parent')
			{
				$value = $this->getProcessor('post')->map('ID', $value);
			}

			if ($field == 'post_author')
			{
				$value = $this->getProcessor('user')->map('ID', $value);
			}

			return $value;
		}

		public function remotePostprocessor($field, $value)
		{

			/*if ($field == 'post_parent')
			{
				$value = $this->map('ID', $value);
			}*/

			return $value;
		}

		protected function uploadFile($sourceurl, $attachmentid, $filedate = '', $filename)
		{

			//$fullsizepath = get_attached_file($attachmentid);

			$uploads = wp_upload_dir($filedate, true);

			$fullsizepath = $uploads['path'].DIRECTORY_SEPARATOR.$filename;

			if (file_exists($fullsizepath))
			{
				$this->wordsync->logWarning("File " . $sourceurl . " already exists! Overwriting...");
			}


			$tmpfile = download_url($sourceurl);

			if (is_wp_error($tmpfile))
			{
				$this->wordsync->logError("File " . $sourceurl . " could not be downloaded. ", array('error' =>$tmpfile));
				return false;
			}

			$move_new_file = @copy( $tmpfile, $fullsizepath);
			unlink($tmpfile);

			if ( false === $move_new_file )
			{
				$this->wordsync->logError("File " . $sourceurl . " could not be moved to directory " . $fullsizepath);
				return false;
			}

			// Set correct file permissions.
			$stat = stat( dirname( $fullsizepath));
			$perms = $stat['mode'] & 0000666;
			@ chmod($fullsizepath, $perms );

			$this->wordsync->log("Attaching file: " . $filedate . "/" . $filename . " to post " . $attachmentid);
			update_post_meta($attachmentid, '_wp_attached_file', $filedate."/".$filename);

			if (wp_update_attachment_metadata( $attachmentid, wp_generate_attachment_metadata( $attachmentid, $fullsizepath )))
				return true;
			else
				return false;
		}

		protected function performCreateAction($change)
		{
			$di = $change->remotedataitem;

			$data = $di->getFields();

			//$data['post_author'] = $this->getProcessor('user')->map('ID', $data['post_author']);
			//$data['post_parent'] = $this->getProcessor('post')->map('ID', $data['post_parent']);

			unset($data['ID']);

			$res = wp_insert_post($data, true);


			if (is_wp_error($res))
			{
				return $this->wordsync->makeResult(false, $res->get_error_message());
			}
			else
			{
				//On success, add this data back into the processor so it can be mapped to the remote data item.
				$data['ID'] = $res;

				$url = $this->wordsync->getSyncher()->remoteUploadsUrl() . '/' . $data['path'];
				$uploadres = $this->uploadFile($url, $data['ID'], $data['dir'], $data['filename']);

				if (!$uploadres)
				{
					wp_delete_attachment($res);
					return $this->wordsync->makeResult(false, 'Unable to upload attachment from ' . $url, array('error' =>$uploadres));
				}

				$this->addNewLocalData($data, $di);
			}

			return $this->wordsync->makeResult(true, 'Attachment Created');
		}

		protected function performUpdateAction($change)
		{
			$di = $change->remotedataitem;
			$id = $change->dataitem->getField('ID');
			$data = $di->getFields();

			//$data['post_author'] = $this->getProcessor('user')->map('ID', $data['post_author']);
			//$data['post_parent'] = $this->getProcessor('post')->map('ID', $data['post_parent']);

			$data['ID'] = $id;

			$res = wp_update_post($data, true);

			if (is_wp_error($res))
			{
				return $this->wordsync->makeResult(false, $res->get_error_message());
			}
			else
			{

				if ($data['image_data'] != $change->dataitem->getField('image_data')) //The image data's do not match, file needs to be uploaded again:
				{

					$url       = $this->wordsync->getSyncher()->remoteUploadsUrl() . '/' . $data['path'];
					$uploadres = $this->uploadFile($url, $data['ID'], $data['dir'], $data['filename']);

					if (!$uploadres)
					{
						return $this->wordsync->makeResult(false, 'Unable to upload attachment from ' . $url, array('error' => $uploadres));
					}
				}


			}

			return $this->wordsync->makeResult(true, 'Attachment Updated');

		}

		protected function performRemoveAction($change)
		{
			$di = $change->dataitem;

			$success = wp_delete_attachment($di->getField('ID'), true) !== false;

			return $this->wordsync->makeResult($success, 'Attachment Deleted');
		}

		protected function afterProcessing()
		{
			//Look through all post meta for the thumbnail_id metatag. Map the possibly incorrect IDs through the attachment processor now that all updates have been done.
			global $wpdb;

			$rows = $wpdb->get_results('SELECT * FROM '.$wpdb->postmeta. ' WHERE meta_key = "_thumbnail_id"', ARRAY_A);

			/*foreach ($rows as $row)
			{
				$newid = $this->map("ID", $row['meta_value']);
				if ($newid != $row['meta_value'])
				{
					$this->wordsync->log("Updated a _thumbnail_id! From ".$row['meta_value']. " to ".$newid);
					update_post_meta($row['post_id'], "_thumbnail_id", $newid);
				}
			}*/
		}


	}