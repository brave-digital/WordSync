<?php

	/**
	 * class-wordsync-user-processor.php
	 *
	 * Date: 2016/12/09
	 * Time: 11:05 AM
	 */
	class BraveWordsync_Taxonomy_Processor extends BraveWordsync_Processor
	{
		protected $slug = 'taxonomy';
		protected $version = '1.0';
		protected $details = array('name'=>'Taxonomies', 'desc'=>'Syncs categories and tags.', 'dashicon'=>'dashicons-tag');

		protected $keyfield = array('slug', 'taxonomy');
		protected $matchfields = array('slug', 'taxonomy');
		protected $valuefields = array('name', 'slug', 'term_group', 'taxonomy', 'description', 'parent', 'meta');
		protected $mapfields = array('term_id', 'term_taxonomy_id');
		protected $namefield = 'name';
		protected $preprocessors = array();
		protected $matchfieldsarestrict = true;

		/**
		 * Loads in user data.
		 * Must SET the $this->localdata array too.
		 */
		public function getLocalData()
		{
			/** @var WPDB $wpdb */
			global $wpdb;

			//$taxonomies = get_taxonomies();
			$args = array(
				'hide_empty' => false,
				'number' => 0
			);
			$term_query = new WP_Term_Query($args);

			$terms = $term_query->get_terms();

			$data = array();

			foreach ($terms as $term)
			{
				/** @var WP_Term $term */
				$thisdata = array(
					'term_id'=> $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'term_group' => $term->term_group,
					'taxonomy' => $term->taxonomy,
					'term_taxonomy_id' => $term->term_taxonomy_id,
					'description'=> $term->description,
					'parent' => $term->parent,
					'parent_slug' => '',
				    'meta'=>array(),
				);

				if ($term->parent > 0)
				{
					//Find the parent's slug so it can be matched.
					foreach ($terms as $tt)
					{
						if ($tt->term_id == $term->parent)
						{
							$thisdata['parent_slug'] = $tt->slug;
							break;
						}
					}
				}

				$excludedmeta = array();
				$meta = $this->sqlQuery($wpdb->termmeta, array('term_id'=>$term->term_id), array('meta_key'=>$excludedmeta));

				foreach ($meta as $row)
				{
					$thisdata['meta'][$row['meta_key']] = $row['meta_value'];
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

			$data = $di->getFields();

			$success = wp_insert_term($data['name'], $data['taxonomy'], array(
				'description'=>$data['description'],
				'parent'=>$this->map('term_id', $data['parent']),
				'slug'=>$data['slug']
			));

			if (!is_wp_error($success)) //Term inserted successfully? $success then holds the Term ID and Term Taxonomy ID. (Example: array('term_id'=>12,'term_taxonomy_id'=>34))
			{
				//Set the new IDs of the data.
				$data['term_id'] = $success['term_id'];
				$data['term_taxonomy_id'] = $success['term_taxonomy_id'];

				foreach ($data['meta'] as $metakey=>$metaval)
				{
					update_term_meta($data['term_id'], $metakey, $metaval);
				}

				$this->addNewLocalData($data, $di);
			}

			return $this->wordsync->makeResult($success, 'Term Created');
		}

		protected function performUpdateAction($change)
		{
			$di = $change->remotedataitem;
			$id = $change->dataitem->getField('term_id');

			$data = $di->getFields();
			$success = wp_update_term($id, $data['taxonomy'], array(
				'description'=>$data['description'],
				'parent'=>$this->map('term_id', $data['parent']),
				'slug'=>$data['slug']
			));

			$success = (!is_wp_error($success));

			return $this->wordsync->makeResult($success, 'Term Updated');

		}

		protected function performRemoveAction($change)
		{
			$di = $change->dataitem;
			$success = wp_delete_term($di->getField('term_id'), $di->getField('taxonomy'));

			return $this->wordsync->makeResult($success === true, 'Term Deleted');
		}


	}